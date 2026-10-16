<?php

namespace App\Services\Payments;

use App\Enums\FeeDirection;
use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventType;
use App\Exceptions\GatewayException;
use App\Integrations\Data\ProviderPaymentData;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\GatewayManager;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\TransactionFee;
use App\Notifications\TransactionStatusNotification;
use App\Services\Fees\FeeCalculator;
use App\Services\Ledger\LedgerService;
use App\Services\Merchants\MerchantLimitGuard;
use App\Services\Providers\ProviderRouter;
use App\Services\Refunds\RefundService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pay In lifecycle.
 *
 * A payment only becomes Completed/Underpaid through applyProviderData() with data
 * from a signature-verified provider callback or an authenticated provider API query
 * (or the sandbox simulator in test mode) - never from a browser redirect.
 */
class PaymentService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ProviderRouter $router,
        private readonly FeeCalculator $fees,
        private readonly LedgerService $ledger,
        private readonly WebhookDispatcher $webhooks,
        private readonly MerchantLimitGuard $limits,
        private readonly RefundService $refunds,
    ) {}

    /**
     * @param  array{amount: string, currency: string, reference: string, network?: ?string, provider?: ?string,
     *     description?: ?string, customer_email?: ?string, success_url?: ?string, cancel_url?: ?string,
     *     lifetime?: ?int, metadata?: ?array}  $input
     */
    public function create(Merchant $merchant, ?MerchantApiKey $apiKey, Mode $mode, array $input): Payment
    {
        if (! $merchant->canTransact($mode)) {
            throw GatewayException::notAllowed($mode === Mode::Live
                ? 'Live payments are not enabled for this account. Complete business verification first.'
                : 'This account cannot create payments.');
        }
        if (SystemSetting::get('maintenance.block_new_payments', false)) {
            throw new GatewayException('service_unavailable', 'New payments are temporarily paused. Please retry later.', 503);
        }

        $amount = Decimal::of($input['amount']);
        $currency = strtoupper($input['currency']);
        $network = $input['network'] ?? null;

        [$provider, $currencyRow] = $this->router->forPayIn($input['provider'] ?? null, $currency, $network, $amount, $mode);
        $this->limits->check($merchant, FeeDirection::PayIn, $currency, $amount, $mode);

        $lifetime = (int) ($input['lifetime'] ?? config('gateway.payments.default_lifetime'));
        $lifetime = max((int) config('gateway.payments.min_lifetime'), min((int) config('gateway.payments.max_lifetime'), $lifetime));

        // Without an explicit network the customer picks one at checkout (among the enabled networks).
        $payment = $this->createOrReuseRecord($merchant, $apiKey, $mode, $provider->id, $amount, $currency, $network ?: null, $input, $lifetime);

        $gateway = $this->gateways->for($provider, $mode);

        try {
            $data = $gateway->createPayment($payment, [
                'lifetime' => $lifetime,
                'callback_url' => route('webhooks.provider', ['provider' => $provider->code]),
            ]);
        } catch (ProviderException $e) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'provider_status' => 'create_failed',
                'failed_at' => now(),
                'review_reason' => 'provider_create_failed',
            ])->save();

            throw $e;
        }

        $payment->forceFill([
            'status' => $data->status ?? PaymentStatus::Pending,
            'provider_status' => $data->providerStatus,
            'provider_payment_id' => $data->providerPaymentId,
            'checkout_url' => $data->checkoutUrl,
            'pay_address' => $data->payAddress,
            'review_reason' => null,
            'failed_at' => null,
            'expires_at' => $data->expiresAt && $data->expiresAt->lt($payment->expires_at) ? $data->expiresAt : $payment->expires_at,
            'last_synced_at' => now(),
        ])->save();

        $this->webhooks->paymentEvent($payment, WebhookEventType::PaymentCreated);

        return $payment->refresh()->load('provider');
    }

    /**
     * Apply verified provider data. Idempotent and safe against out-of-order callbacks:
     * the payment row is locked, transitions are validated, and the ledger settlement
     * is keyed so it can only ever be posted once.
     *
     * @param  string  $source  webhook | api | sandbox | reconciliation
     */
    public function applyProviderData(Payment $payment, ProviderPaymentData $data, string $source): Payment
    {
        $before = null;

        $updated = DB::transaction(function () use ($payment, $data, $source, &$before) {
            /** @var Payment $p */
            $p = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $current = $before = $p->status;

            $p->provider_status = $data->providerStatus;
            $p->last_synced_at = now();
            $p->provider_payment_id ??= $data->providerPaymentId;
            $p->pay_address = $data->payAddress ?: $p->pay_address;
            $p->payer_address = $data->payerAddress ?: $p->payer_address;
            $p->tx_hash = $data->txHash ?: $p->tx_hash;
            if ($source === 'webhook') {
                $p->callback_verified = true;
                $p->callback_verified_at = now();
            }

            $target = $data->status;
            if ($target === PaymentStatus::Cancelled && $p->expires_at && $p->expires_at->isPast()) {
                $target = PaymentStatus::Expired;
            }
            if ($current === PaymentStatus::RefundPending && $target === PaymentStatus::Completed) {
                $target = Decimal::lt((string) $p->received_amount, (string) $p->amount) ? PaymentStatus::Underpaid : PaymentStatus::Completed;
            }

            if ($target === null || $target === $current || ! $current->canTransitionTo($target)) {
                if ($target !== null && $target !== $current) {
                    Log::info('Ignored payment status transition', [
                        'payment' => $p->public_id, 'from' => $current->value, 'to' => $target->value, 'source' => $source,
                    ]);
                }
                $p->save();

                return $p;
            }

            if ($target->isSettled() && ! $current->isSettled() && $target !== PaymentStatus::Refunded && $target !== PaymentStatus::RefundPending) {
                if (! $this->settle($p, $data, $target, $current)) {
                    $p->save();

                    return $p;
                }
            } elseif ($target === PaymentStatus::Refunded) {
                $p->status = $target;
                $p->save();
                if (! $this->refunds->completeForPayment($p)) {
                    $p->forceFill(['needs_review' => true, 'review_reason' => 'refund_outside_platform'])->save();
                }
            } elseif ($current === PaymentStatus::RefundPending && $target->isSettled()) {
                $p->status = $target;
                $p->save();
                $this->refunds->failForPayment($p, 'Provider reported refund failure ('.$data->providerStatus.')');
            } else {
                if ($target === PaymentStatus::RefundPending && ! $this->refunds->hasPendingForPayment($p)) {
                    $p->needs_review = true;
                    $p->review_reason = 'refund_outside_platform';
                }
                $p->status = $target;
                if ($target === PaymentStatus::Failed) {
                    $p->failed_at = now();
                }
                $p->save();
            }

            return $p;
        });

        $changedTo = $updated->status !== $before ? $updated->status : null;

        if ($changedTo !== null) {
            $updated->load('provider', 'merchant');
            if ($event = WebhookEventType::forPaymentStatus($changedTo)) {
                $this->webhooks->paymentEvent($updated, $event);
            }
            if ($changedTo === PaymentStatus::Completed || $changedTo === PaymentStatus::Underpaid) {
                $this->notifyMerchant($updated);
            }
        }

        return $updated;
    }

    /** Authenticated server-to-server status check with the provider. */
    public function syncWithProvider(Payment $payment, string $source = 'api'): Payment
    {
        $payment->loadMissing('provider');
        $data = $this->gateways->for($payment->provider, $payment->mode)->fetchPayment($payment);

        return $this->applyProviderData($payment, $data, $source);
    }

    /**
     * Record the received amount and final fees, then post the ledger settlement.
     * Returns false when the data cannot be settled automatically (flagged for review).
     */
    private function settle(Payment $p, ProviderPaymentData $data, PaymentStatus $target, PaymentStatus $current): bool
    {
        $received = $data->receivedAmount ?? ($target === PaymentStatus::Completed ? Decimal::of((string) $p->amount) : null);

        if ($received === null || ! Decimal::isPositive($received)) {
            $p->forceFill(['needs_review' => true, 'review_reason' => 'missing_received_amount']);

            return false;
        }

        $receivedCurrency = strtoupper($data->receivedCurrency ?: $p->currency);
        if ($receivedCurrency !== $p->currency) {
            // Never combine currencies without a recorded conversion; hold for manual reconciliation.
            $p->forceFill([
                'needs_review' => true,
                'review_reason' => "currency_mismatch: received {$received} {$receivedCurrency}",
                'status' => $p->status->canTransitionTo(PaymentStatus::OnHold) ? PaymentStatus::OnHold : $p->status,
            ]);

            return false;
        }

        if (in_array($current, [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled], true)) {
            $p->needs_review = true;
            $p->review_reason = 'late_payment';
        }

        /** @var TransactionFee|null $quoted */
        $quoted = $p->fees()->where('kind', 'service')->where('stage', 'quote')->first();
        $final = $quoted
            ? $this->fees->requote($quoted, $received)
            : $this->fees->quote(FeeDirection::PayIn, $p->merchant_id, $p->provider_id, $p->currency, $received);
        $this->fees->record($p, 'service', 'final', $final);

        $p->forceFill([
            'status' => $target,
            'received_amount' => $received,
            'received_currency' => $receivedCurrency,
            'service_fee' => $final->amount,
            'provider_fee' => $data->providerFee ?? Decimal::zero(),
            'merchant_net_amount' => Decimal::sub($received, $final->amount),
            'completed_at' => now(),
        ])->save();

        $this->ledger->postPaymentSettlement($p);

        return true;
    }

    private function createOrReuseRecord(Merchant $merchant, ?MerchantApiKey $apiKey, Mode $mode, int $providerId, string $amount, string $currency, ?string $network, array $input, int $lifetime): Payment
    {
        $reference = $input['reference'];

        return DB::transaction(function () use ($merchant, $apiKey, $mode, $providerId, $amount, $currency, $network, $input, $lifetime, $reference) {
            $existing = Payment::query()
                ->where('merchant_id', $merchant->id)->where('mode', $mode->value)->where('merchant_reference', $reference)
                ->lockForUpdate()->first();

            if ($existing) {
                $retryable = $existing->status === PaymentStatus::Failed
                    && $existing->review_reason === 'provider_create_failed'
                    && $existing->provider_payment_id === null
                    && Decimal::eq((string) $existing->amount, $amount)
                    && $existing->currency === $currency;

                if (! $retryable) {
                    throw GatewayException::duplicateReference($reference, $existing->public_id);
                }

                // The previous attempt never reached the provider successfully: retry with a fresh provider reference.
                $attempt = preg_match('/r(\d+)$/', $existing->provider_order_id, $m) ? ((int) $m[1]) + 1 : 1;
                $existing->forceFill([
                    'status' => PaymentStatus::Created,
                    'provider_id' => $providerId,
                    'provider_order_id' => preg_replace('/r\d+$/', '', $existing->provider_order_id).'r'.$attempt,
                    'expires_at' => now()->addSeconds($lifetime),
                ])->save();

                return $existing;
            }

            $quote = $this->fees->quote(FeeDirection::PayIn, $merchant->id, $providerId, $currency, $amount);

            try {
                $payment = Payment::query()->create([
                    'merchant_id' => $merchant->id,
                    'api_key_id' => $apiKey?->id,
                    'mode' => $mode->value,
                    'provider_id' => $providerId,
                    'merchant_reference' => $reference,
                    'description' => $input['description'] ?? null,
                    'amount' => $amount,
                    'currency' => $currency,
                    'network' => $network,
                    'service_fee' => $quote->amount,
                    'status' => PaymentStatus::Created->value,
                    'customer_email' => $input['customer_email'] ?? null,
                    'success_url' => $input['success_url'] ?? null,
                    'cancel_url' => $input['cancel_url'] ?? null,
                    'metadata' => $input['metadata'] ?? null,
                    'expires_at' => now()->addSeconds($lifetime),
                ]);
            } catch (UniqueConstraintViolationException) {
                $other = Payment::query()->where('merchant_id', $merchant->id)->where('mode', $mode->value)->where('merchant_reference', $reference)->first();
                throw GatewayException::duplicateReference($reference, $other?->public_id ?? '');
            }

            $this->fees->record($payment, 'service', 'quote', $quote);

            return $payment;
        });
    }

    private function notifyMerchant(Payment $payment): void
    {
        foreach ($payment->merchant->users as $user) {
            if ($user->wantsNotification('payment_completed')) {
                $user->notify(new TransactionStatusNotification(
                    'payment',
                    $payment->public_id,
                    "Payment {$payment->status->label()}",
                    'Pay In '.$payment->merchant_reference.': '.Decimal::format((string) $payment->received_amount).' '.$payment->currency.' received.',
                    route('merchant.payments.show', $payment),
                ));
            }
        }
    }
}

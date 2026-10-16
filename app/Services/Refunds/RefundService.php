<?php

namespace App\Services\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WebhookEventType;
use App\Exceptions\GatewayException;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Integrations\GatewayManager;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Full refunds of settled payments, initiated by an authorised admin.
 * The refunded amount (the received amount) is reserved from the merchant's
 * available balance first; platform service fees are not returned.
 */
class RefundService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly LedgerService $ledger,
        private readonly WebhookDispatcher $webhooks,
        private readonly AuditLogger $audit,
    ) {}

    public function request(Payment $payment, User $actor, ?string $address, ?string $reason): Refund
    {
        $payment->loadMissing('provider');
        $gateway = $this->gateways->for($payment->provider, $payment->mode);

        if (! $gateway->capabilities()->refund || ! $payment->provider->refund_enabled) {
            throw GatewayException::unsupported("Refunds are not available for {$payment->provider->name}.");
        }

        $refund = DB::transaction(function () use ($payment, $actor, $address, $reason) {
            $p = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($p->status, [PaymentStatus::Completed, PaymentStatus::Underpaid], true)) {
                throw new GatewayException('invalid_state', 'Only completed payments can be refunded.');
            }
            if ($this->hasPendingForPayment($p)) {
                throw new GatewayException('refund_in_progress', 'A refund for this payment is already in progress.', 409);
            }

            $refund = Refund::query()->create([
                'payment_id' => $p->id,
                'merchant_id' => $p->merchant_id,
                'mode' => $p->mode->value,
                'provider_id' => $p->provider_id,
                'amount' => Decimal::of((string) $p->received_amount),
                'currency' => $p->currency,
                'address' => $address,
                'reason' => $reason,
                'status' => RefundStatus::Requested->value,
                'requested_by' => $actor->id,
            ]);

            $this->ledger->postRefundReserve($refund);
            $p->forceFill(['status' => PaymentStatus::RefundPending])->save();
            $this->audit->log('refund.requested', $refund, new: ['amount' => $refund->amount, 'currency' => $refund->currency, 'payment' => $p->public_id], user: $actor);

            return $refund;
        });

        try {
            $result = $gateway->refundPayment($refund, $payment->refresh());
        } catch (ProviderException|UnsupportedOperationException $e) {
            $this->fail($refund, $e instanceof ProviderException ? $e->safeMessage : $e->getMessage());

            throw $e instanceof ProviderException
                ? new GatewayException($e->errorCode, $e->safeMessage, 502)
                : GatewayException::unsupported($e->getMessage());
        }

        $refund->forceFill([
            'provider_refund_id' => $result->providerRefundId,
            'provider_status' => $result->providerStatus,
        ])->save();

        match ($result->status) {
            RefundStatus::Completed => DB::transaction(function () use ($refund) {
                $p = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
                $p->forceFill(['status' => PaymentStatus::Refunded])->save();
                $this->completeForPayment($p);
            }),
            RefundStatus::Failed => $this->fail($refund, 'Provider rejected the refund ('.$result->providerStatus.')'),
            default => $refund->forceFill(['status' => RefundStatus::Processing])->save(),
        };

        if ($result->status === RefundStatus::Completed) {
            $this->webhooks->paymentEvent($payment->refresh(), WebhookEventType::PaymentRefunded);
        }

        return $refund->refresh();
    }

    public function hasPendingForPayment(Payment $payment): bool
    {
        return Refund::query()->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value])->exists();
    }

    /** Called when the provider confirms the refund. Returns false if there was no refund in flight. */
    public function completeForPayment(Payment $payment): bool
    {
        $refund = Refund::query()->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value])
            ->lockForUpdate()->first();

        if (! $refund) {
            return false;
        }

        $this->ledger->postRefundSettlement($refund);
        $refund->forceFill(['status' => RefundStatus::Completed, 'completed_at' => now()])->save();

        return true;
    }

    public function failForPayment(Payment $payment, string $reason): void
    {
        $refund = Refund::query()->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value])
            ->first();

        if ($refund) {
            $this->ledger->postRefundRelease($refund);
            $refund->forceFill(['status' => RefundStatus::Failed, 'failure_reason' => $reason])->save();
        }
    }

    private function fail(Refund $refund, string $reason): void
    {
        DB::transaction(function () use ($refund, $reason) {
            $p = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $this->ledger->postRefundRelease($refund);
            $refund->forceFill(['status' => RefundStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 255)])->save();

            if ($p->status === PaymentStatus::RefundPending) {
                $p->forceFill([
                    'status' => Decimal::lt((string) $p->received_amount, (string) $p->amount) ? PaymentStatus::Underpaid : PaymentStatus::Completed,
                ])->save();
            }
        });
    }
}

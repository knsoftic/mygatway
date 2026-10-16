<?php

namespace App\Services\Payouts;

use App\Enums\FeeDirection;
use App\Enums\Mode;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Enums\WebhookEventType;
use App\Exceptions\GatewayException;
use App\Integrations\Data\ProviderPayoutData;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Integrations\GatewayManager;
use App\Jobs\SubmitPayout;
use App\Jobs\SyncPayoutStatus;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\Payout;
use App\Models\PayoutApproval;
use App\Models\SupportedCurrency;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\TransactionStatusNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Fees\FeeCalculator;
use App\Services\Ledger\LedgerService;
use App\Services\Merchants\MerchantLimitGuard;
use App\Services\Providers\ProviderRouter;
use App\Services\Risk\RiskMonitor;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pay Out lifecycle:
 *   requested -> (under_review) -> approved -> processing -> completed | failed
 *   requested/under_review -> rejected | cancelled
 *
 * Funds are reserved in the ledger at request time, so concurrent requests can never
 * overdraw a balance. State changes use compare-and-swap updates, so a payout can only
 * be approved/submitted once even with several workers or admins acting at the same time.
 */
class PayoutService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ProviderRouter $router,
        private readonly FeeCalculator $fees,
        private readonly LedgerService $ledger,
        private readonly WebhookDispatcher $webhooks,
        private readonly MerchantLimitGuard $limits,
        private readonly AuditLogger $audit,
        private readonly RiskMonitor $risk,
    ) {}

    /**
     * @param  array{amount: string, currency: string, reference: string, destination_type: string, destination: string,
     *     network?: ?string, provider?: ?string, memo?: ?string, description?: ?string, metadata?: ?array}  $input
     */
    public function request(Merchant $merchant, ?MerchantApiKey $apiKey, ?User $user, Mode $mode, array $input): Payout
    {
        if (! $merchant->canTransact($mode)) {
            throw GatewayException::notAllowed($mode === Mode::Live
                ? 'Live payouts are not enabled for this account.'
                : 'This account cannot request payouts.');
        }
        if (SystemSetting::get('maintenance.block_new_payouts', false)) {
            throw new GatewayException('service_unavailable', 'New payouts are temporarily paused. Please retry later.', 503);
        }

        $amount = Decimal::of($input['amount']);
        $currency = strtoupper($input['currency']);
        $destinationType = PayoutDestinationType::from($input['destination_type']);

        [$provider, $currencyRow] = $this->router->forPayOut($input['provider'] ?? null, $currency, $input['network'] ?? null, $amount, $mode, $destinationType);
        $this->limits->check($merchant, FeeDirection::PayOut, $currency, $amount, $mode);

        $serviceFee = $this->fees->quote(FeeDirection::PayOut, $merchant->id, $provider->id, $currency, $amount);
        $networkFee = $this->networkFeeEstimate($currencyRow, $amount);
        $total = Decimal::add(Decimal::add($amount, $serviceFee->amount), $networkFee);

        $payout = DB::transaction(function () use ($merchant, $apiKey, $user, $mode, $input, $provider, $currencyRow, $amount, $currency, $destinationType, $serviceFee, $networkFee, $total) {
            try {
                $payout = Payout::query()->create([
                    'merchant_id' => $merchant->id,
                    'api_key_id' => $apiKey?->id,
                    'requested_by_user_id' => $user?->id,
                    'mode' => $mode->value,
                    'provider_id' => $provider->id,
                    'merchant_reference' => $input['reference'],
                    'description' => $input['description'] ?? null,
                    'amount' => $amount,
                    'currency' => $currency,
                    'network' => ($input['network'] ?? null) ?: ($currencyRow->network ?: null),
                    'destination_type' => $destinationType->value,
                    'destination' => trim($input['destination']),
                    'destination_memo' => $input['memo'] ?? null,
                    'service_fee' => $serviceFee->amount,
                    'network_fee' => $networkFee,
                    'total_debit' => $total,
                    'status' => PayoutStatus::Requested->value,
                    'metadata' => $input['metadata'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = Payout::query()->where('merchant_id', $merchant->id)->where('mode', $mode->value)
                    ->where('merchant_reference', $input['reference'])->first();
                throw GatewayException::duplicateReference($input['reference'], $existing?->public_id ?? '');
            }

            $this->fees->record($payout, 'service', 'final', $serviceFee);
            if (Decimal::isPositive($networkFee)) {
                $this->fees->record($payout, 'network', 'final', $this->fees->compute($amount, $currency, (string) ($currencyRow->provider_fee_percent ?? '0'),
                    (string) ($currencyRow->provider_fee_fixed ?? '0'), null, null, null, ['source' => 'provider_published_commission', 'supported_currency_id' => $currencyRow->id], false));
            }

            // Throws insufficient_balance (and rolls everything back) if available funds do not cover the total debit.
            $this->ledger->postPayoutReserve($payout);
            $this->logApproval($payout, $user, 'requested', null, PayoutStatus::Requested, $apiKey ? 'Requested via API key '.$apiKey->key_id : null);

            return $payout;
        });

        $this->webhooks->payoutEvent($payout, WebhookEventType::PayoutRequested);
        $this->risk->checkPayout($payout);

        if ($mode === Mode::Test) {
            $this->approve($payout, null, 'Auto-approved in test mode');
        } elseif (SystemSetting::get('payouts.manual_approval', config('gateway.payouts.manual_approval'))) {
            $this->transition($payout, [PayoutStatus::Requested], PayoutStatus::UnderReview);
            $this->logApproval($payout, null, 'note', PayoutStatus::Requested, PayoutStatus::UnderReview, 'Queued for administrator approval');
        } else {
            $this->approve($payout, null, 'Auto-approved (manual approval disabled)');
        }

        return $payout->refresh()->load('provider');
    }

    public function approve(Payout $payout, ?User $admin, ?string $note = null): Payout
    {
        $from = $payout->refresh()->status;
        if (! $this->transition($payout, [PayoutStatus::Requested, PayoutStatus::UnderReview], PayoutStatus::Approved, [
            'approved_at' => now(), 'approved_by' => $admin?->id,
        ])) {
            throw new GatewayException('invalid_state', "Payout cannot be approved from status {$from->value}.", 409);
        }

        $this->logApproval($payout, $admin, 'approve', $from, PayoutStatus::Approved, $note);
        if ($admin) {
            $this->audit->log('payout.approved', $payout, context: ['note' => $note], user: $admin, merchantId: $payout->merchant_id);
        }
        $this->webhooks->payoutEvent($payout->refresh(), WebhookEventType::PayoutApproved);

        SubmitPayout::dispatch($payout->id)->afterCommit();

        return $payout->refresh();
    }

    public function reject(Payout $payout, User $admin, string $reason): Payout
    {
        $from = $payout->refresh()->status;

        DB::transaction(function () use ($payout, $admin, $reason, $from) {
            if (! $this->transition($payout, [PayoutStatus::Requested, PayoutStatus::UnderReview], PayoutStatus::Rejected, [
                'rejected_at' => now(), 'rejected_by' => $admin->id, 'rejection_reason' => $reason,
            ])) {
                throw new GatewayException('invalid_state', "Payout cannot be rejected from status {$from->value}.", 409);
            }
            $this->ledger->postPayoutRelease($payout->refresh());
            $this->logApproval($payout, $admin, 'reject', $from, PayoutStatus::Rejected, $reason);
            $this->audit->log('payout.rejected', $payout, context: ['reason' => $reason], user: $admin, merchantId: $payout->merchant_id);
        });

        $this->afterFinal($payout->refresh(), WebhookEventType::PayoutRejected);

        return $payout;
    }

    public function cancel(Payout $payout, ?User $actor, string $reason = 'Cancelled by merchant'): Payout
    {
        $from = $payout->refresh()->status;

        DB::transaction(function () use ($payout, $actor, $reason, $from) {
            if (! $this->transition($payout, [PayoutStatus::Requested, PayoutStatus::UnderReview], PayoutStatus::Cancelled, ['cancelled_at' => now()])) {
                throw new GatewayException('invalid_state', "Payout can no longer be cancelled (status {$from->value}).", 409);
            }
            $this->ledger->postPayoutRelease($payout->refresh());
            $this->logApproval($payout, $actor, 'cancel', $from, PayoutStatus::Cancelled, $reason);
        });

        $this->afterFinal($payout->refresh(), WebhookEventType::PayoutCancelled);

        return $payout;
    }

    /** Send an approved payout to the provider. Safe to call more than once. */
    public function submit(Payout $payout): Payout
    {
        if (! $this->transition($payout, [PayoutStatus::Approved], PayoutStatus::Processing, ['submitted_at' => now()])) {
            return $payout->refresh();
        }

        $payout->refresh()->load('provider');
        $this->webhooks->payoutEvent($payout, WebhookEventType::PayoutProcessing);
        $gateway = $this->gateways->for($payout->provider, $payout->mode);

        try {
            $data = $gateway->createPayout($payout, route('webhooks.provider', ['provider' => $payout->provider->code]));
        } catch (UnsupportedOperationException $e) {
            return $this->markFailed($payout, $e->getMessage());
        } catch (ProviderException $e) {
            if ($e->retryable) {
                // Outcome unknown (e.g. timeout): the provider reference is idempotent, so check status before retrying.
                $payout->forceFill(['needs_review' => true, 'review_reason' => 'submission_uncertain: '.$e->errorCode])->save();
                SyncPayoutStatus::dispatch($payout->id, resubmitIfMissing: true)->delay(now()->addMinutes(2));

                return $payout;
            }

            return $this->markFailed($payout, $e->safeMessage, $e->errorCode === 'provider_insufficient_funds');
        }

        return $this->applyProviderData($payout, $data, 'api');
    }

    /** Re-send after an uncertain submission. The provider reference is unchanged, so no duplicate payout can result. */
    public function resubmit(Payout $payout): Payout
    {
        $payout->load('provider');
        if ($payout->status !== PayoutStatus::Processing) {
            return $payout;
        }

        try {
            $data = $this->gateways->for($payout->provider, $payout->mode)
                ->createPayout($payout, route('webhooks.provider', ['provider' => $payout->provider->code]));
        } catch (ProviderException $e) {
            if ($e->errorCode === 'duplicate_reference') {
                return $this->syncWithProvider($payout);
            }
            if ($e->retryable) {
                throw $e;
            }

            return $this->markFailed($payout, $e->safeMessage);
        }

        $payout->forceFill(['needs_review' => false, 'review_reason' => null])->save();

        return $this->applyProviderData($payout, $data, 'api');
    }

    public function applyProviderData(Payout $payout, ProviderPayoutData $data, string $source): Payout
    {
        $before = null;

        $updated = DB::transaction(function () use ($payout, $data, $source, &$before) {
            /** @var Payout $p */
            $p = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $current = $before = $p->status;

            $p->provider_status = $data->providerStatus;
            $p->provider_payout_id ??= $data->providerPayoutId;
            $p->tx_hash = $data->txHash ?: $p->tx_hash;
            $p->last_synced_at = now();

            $target = $data->status;
            // A provider result can arrive before our own "processing" write is visible.
            if ($current === PayoutStatus::Approved && in_array($target, [PayoutStatus::Completed, PayoutStatus::Failed], true)) {
                $current = PayoutStatus::Processing;
            }

            if ($target === null || $target === $p->status || ! $current->canTransitionTo($target)) {
                if ($target !== null && $target !== $p->status) {
                    Log::info('Ignored payout status transition', ['payout' => $p->public_id, 'from' => $p->status->value, 'to' => $target->value, 'source' => $source]);
                }
                $p->save();

                return $p;
            }

            if ($target === PayoutStatus::Completed) {
                $p->forceFill([
                    'status' => $target,
                    'provider_fee' => $data->providerFee ?? Decimal::zero(),
                    'completed_at' => now(),
                    'needs_review' => false,
                    'review_reason' => null,
                ])->save();
                $this->ledger->postPayoutSettlement($p);
            } elseif ($target === PayoutStatus::Failed) {
                $p->forceFill([
                    'status' => $target,
                    'failure_reason' => mb_substr((string) ($data->failureReason ?? 'Provider reported failure'), 0, 255),
                    'failed_at' => now(),
                ])->save();
                $this->ledger->postPayoutRelease($p);
            } else {
                $p->status = $target;
                $p->save();
            }

            return $p;
        });

        if ($updated->status !== $before) {
            $event = WebhookEventType::forPayoutStatus($updated->status);
            $updated->status->isFinal() ? $this->afterFinal($updated, $event) : ($event && $this->webhooks->payoutEvent($updated, $event));
        }

        return $updated;
    }

    public function syncWithProvider(Payout $payout): Payout
    {
        $payout->loadMissing('provider');
        $data = $this->gateways->for($payout->provider, $payout->mode)->fetchPayout($payout);

        return $this->applyProviderData($payout, $data, 'api');
    }

    private function markFailed(Payout $payout, string $reason, bool $platformIssue = false): Payout
    {
        $updated = DB::transaction(function () use ($payout, $reason, $platformIssue) {
            $p = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($p->status->isFinal()) {
                return $p;
            }
            $p->forceFill([
                'status' => PayoutStatus::Failed,
                'failure_reason' => mb_substr($reason, 0, 255),
                'failed_at' => now(),
                'needs_review' => $platformIssue,
                'review_reason' => $platformIssue ? 'provider_balance_insufficient' : null,
            ])->save();
            $this->ledger->postPayoutRelease($p);

            return $p;
        });

        $this->afterFinal($updated, WebhookEventType::PayoutFailed);

        return $updated;
    }

    private function afterFinal(Payout $payout, ?WebhookEventType $event): void
    {
        if ($event) {
            $this->webhooks->payoutEvent($payout, $event);
        }

        foreach ($payout->merchant->users as $user) {
            if ($user->wantsNotification('payout_status')) {
                $user->notify(new TransactionStatusNotification(
                    'payout',
                    $payout->public_id,
                    "Payout {$payout->status->label()}",
                    'Payout '.$payout->merchant_reference.' of '.Decimal::format((string) $payout->amount).' '.$payout->currency.' is '.strtolower($payout->status->label()).'.',
                    route('merchant.payouts.show', $payout),
                ));
            }
        }
    }

    /**
     * Compare-and-swap status update; returns false when the payout was not in an allowed state.
     *
     * @param  list<PayoutStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function transition(Payout $payout, array $from, PayoutStatus $to, array $extra = []): bool
    {
        $affected = Payout::query()
            ->whereKey($payout->id)
            ->whereIn('status', array_map(fn (PayoutStatus $s) => $s->value, $from))
            ->update(array_merge($extra, ['status' => $to->value, 'updated_at' => now()]));

        return $affected === 1;
    }

    private function logApproval(Payout $payout, ?User $user, string $action, ?PayoutStatus $from, ?PayoutStatus $to, ?string $note): void
    {
        PayoutApproval::query()->create([
            'payout_id' => $payout->id,
            'user_id' => $user?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'note' => $note,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** Provider's published commission for this currency/network, if the platform passes it on. */
    private function networkFeeEstimate(SupportedCurrency $row, string $amount): string
    {
        if (! SystemSetting::get('payouts.pass_network_fee_to_merchant', config('gateway.payouts.pass_network_fee_to_merchant'))) {
            return Decimal::zero();
        }

        $fixed = Decimal::of((string) ($row->provider_fee_fixed ?? '0'));
        $percent = (string) ($row->provider_fee_percent ?? '0');

        return Decimal::add($fixed, Decimal::percentOf($amount, $percent));
    }
}

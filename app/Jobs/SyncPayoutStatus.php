<?php

namespace App\Jobs;

use App\Enums\PayoutStatus;
use App\Integrations\Exceptions\ProviderException;
use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Authenticated status query for an in-flight payout. When a submission timed out
 * and the provider has no record of it, the payout is re-sent with the same
 * idempotent provider reference.
 */
class SyncPayoutStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public readonly int $payoutId, public readonly bool $resubmitIfMissing = false)
    {
        $this->onQueue('payouts');
    }

    public function handle(PayoutService $payouts): void
    {
        $payout = Payout::query()->find($this->payoutId);
        if (! $payout || $payout->status !== PayoutStatus::Processing) {
            return;
        }

        try {
            $payouts->syncWithProvider($payout);
        } catch (ProviderException $e) {
            $notFound = in_array($e->errorCode, ['provider_order_not_found', 'provider_validation_error', 'provider_error'], true)
                && ($e->httpStatus === 404 || $e->httpStatus === 422 || str_contains(strtolower($e->getMessage()), 'not found'));

            if ($this->resubmitIfMissing && $notFound) {
                $payouts->resubmit($payout);

                return;
            }

            throw $e;
        }
    }
}

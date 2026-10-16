<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends an approved payout to its provider. PayoutService::submit() moves the payout
 * approved -> processing with a compare-and-swap update, so duplicates are harmless.
 */
class SubmitPayout implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $payoutId)
    {
        $this->onQueue('payouts');
    }

    public function uniqueId(): string
    {
        return (string) $this->payoutId;
    }

    public function handle(PayoutService $payouts): void
    {
        if ($payout = Payout::query()->find($this->payoutId)) {
            $payouts->submit($payout);
        }
    }
}

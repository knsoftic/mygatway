<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Authenticated provider status query for an open payment (fallback for missed callbacks).
 */
class SyncPaymentStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $paymentId)
    {
        $this->onQueue('sync');
    }

    public function handle(PaymentService $payments): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if ($payment && $payment->mode->value === 'live' && ($payment->status->isOpen() || $payment->status->value === 'refund_pending')) {
            $payments->syncWithProvider($payment, 'api');
        }
    }
}

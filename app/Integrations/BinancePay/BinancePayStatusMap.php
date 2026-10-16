<?php

namespace App\Integrations\BinancePay;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;

/**
 * Maps documented Binance Pay statuses onto platform statuses.
 */
final class BinancePayStatusMap
{
    /** Query Order v2 `status`. */
    public static function order(string $status): ?PaymentStatus
    {
        return match ($status) {
            'INITIAL' => PaymentStatus::Pending,
            'PENDING' => PaymentStatus::Processing,
            'PAID' => PaymentStatus::Completed,
            'CANCELED' => PaymentStatus::Cancelled,
            'ERROR' => PaymentStatus::Failed,
            'EXPIRED' => PaymentStatus::Expired,
            'REFUNDING' => PaymentStatus::RefundPending,
            'REFUNDED', 'FULL_REFUNDED' => PaymentStatus::Refunded,
            default => null,
        };
    }

    /** Order webhook `bizStatus`. PAY_CLOSED needs a status query (closed vs. expired). */
    public static function orderWebhook(string $bizStatus): ?PaymentStatus
    {
        return match ($bizStatus) {
            'PAY_SUCCESS' => PaymentStatus::Completed,
            'PAY_FAIL' => PaymentStatus::Failed,
            default => null,
        };
    }

    /**
     * Payout query: a single-transfer batch is decided by its detail status
     * when present, otherwise by the batch status.
     */
    public static function payout(string $batchStatus, ?string $detailStatus): ?PayoutStatus
    {
        if ($detailStatus !== null && $detailStatus !== '') {
            return match ($detailStatus) {
                'SUCCESS' => PayoutStatus::Completed,
                'FAIL', 'REFUNDED' => PayoutStatus::Failed,
                'AWAITING_RECEIPT', 'PROCESSING' => PayoutStatus::Processing,
                default => null,
            };
        }

        return match ($batchStatus) {
            'ACCEPTED', 'PROCESSING', 'PART_SUCCESS' => PayoutStatus::Processing,
            'SUCCESS' => PayoutStatus::Completed,
            'FAILED', 'CANCELED' => PayoutStatus::Failed,
            default => null,
        };
    }

    public static function refund(string $refundStatus): RefundStatus
    {
        return match ($refundStatus) {
            'REFUNDED' => RefundStatus::Completed,
            'CANCELLED', 'CANCELED', 'FAIL', 'FAILED' => RefundStatus::Failed,
            default => RefundStatus::Processing,
        };
    }
}

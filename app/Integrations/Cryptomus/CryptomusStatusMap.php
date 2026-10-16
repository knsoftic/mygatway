<?php

namespace App\Integrations\Cryptomus;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;

/**
 * Maps documented Cryptomus statuses onto platform statuses.
 *
 * Payment statuses: doc.cryptomus.com/merchant-api/payments/payment-statuses
 * Payout statuses:  doc.cryptomus.com/merchant-api/payouts/payout-statuses
 */
final class CryptomusStatusMap
{
    public static function payment(string $status, bool $expired = false): ?PaymentStatus
    {
        return match ($status) {
            'check' => PaymentStatus::Pending,
            'process', 'wrong_amount_waiting' => PaymentStatus::Processing,
            'confirm_check' => PaymentStatus::Confirming,
            'paid', 'paid_over' => PaymentStatus::Completed,
            // Client paid less than required and the invoice is final: funds were received.
            'wrong_amount' => PaymentStatus::Underpaid,
            'fail', 'system_fail' => PaymentStatus::Failed,
            // "Payment cancelled, the client did not pay" - treat as expiry once the lifetime has passed.
            'cancel' => $expired ? PaymentStatus::Expired : PaymentStatus::Cancelled,
            'locked' => PaymentStatus::OnHold,
            'refund_process' => PaymentStatus::RefundPending,
            'refund_paid' => PaymentStatus::Refunded,
            // Refund failed: the invoice returns to its paid state (PaymentService picks
            // Completed or Underpaid from the received amount and releases the refund reserve).
            'refund_fail' => PaymentStatus::Completed,
            default => null,
        };
    }

    public static function payout(string $status): ?PayoutStatus
    {
        return match ($status) {
            'process', 'check' => PayoutStatus::Processing,
            'paid' => PayoutStatus::Completed,
            'fail', 'cancel', 'system_fail' => PayoutStatus::Failed,
            default => null,
        };
    }
}

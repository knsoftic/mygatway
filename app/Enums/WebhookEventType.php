<?php

namespace App\Enums;

enum WebhookEventType: string
{
    case PaymentCreated = 'payment.created';
    case PaymentPending = 'payment.pending';
    case PaymentProcessing = 'payment.processing';
    case PaymentCompleted = 'payment.completed';
    case PaymentUnderpaid = 'payment.underpaid';
    case PaymentFailed = 'payment.failed';
    case PaymentExpired = 'payment.expired';
    case PaymentCancelled = 'payment.cancelled';
    case PaymentOnHold = 'payment.on_hold';
    case PaymentRefunded = 'payment.refunded';
    case PayoutRequested = 'payout.requested';
    case PayoutApproved = 'payout.approved';
    case PayoutProcessing = 'payout.processing';
    case PayoutCompleted = 'payout.completed';
    case PayoutFailed = 'payout.failed';
    case PayoutRejected = 'payout.rejected';
    case PayoutCancelled = 'payout.cancelled';
    // Sent on demand from the dashboard to test an endpoint.
    case Ping = 'webhook.ping';

    public static function forPaymentStatus(PaymentStatus $status): ?self
    {
        return match ($status) {
            PaymentStatus::Created => self::PaymentCreated,
            PaymentStatus::Pending => self::PaymentPending,
            PaymentStatus::Processing, PaymentStatus::Confirming => self::PaymentProcessing,
            PaymentStatus::Completed => self::PaymentCompleted,
            PaymentStatus::Underpaid => self::PaymentUnderpaid,
            PaymentStatus::Failed => self::PaymentFailed,
            PaymentStatus::Expired => self::PaymentExpired,
            PaymentStatus::Cancelled => self::PaymentCancelled,
            PaymentStatus::OnHold => self::PaymentOnHold,
            PaymentStatus::Refunded => self::PaymentRefunded,
            PaymentStatus::RefundPending => null,
        };
    }

    public static function forPayoutStatus(PayoutStatus $status): ?self
    {
        return match ($status) {
            PayoutStatus::Requested, PayoutStatus::UnderReview => self::PayoutRequested,
            PayoutStatus::Approved => self::PayoutApproved,
            PayoutStatus::Processing => self::PayoutProcessing,
            PayoutStatus::Completed => self::PayoutCompleted,
            PayoutStatus::Failed => self::PayoutFailed,
            PayoutStatus::Rejected => self::PayoutRejected,
            PayoutStatus::Cancelled => self::PayoutCancelled,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}

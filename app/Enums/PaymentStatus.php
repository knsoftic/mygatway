<?php

namespace App\Enums;

/**
 * Unified Pay In status. Provider statuses are mapped onto these values by
 * each provider's status mapper; a payment only reaches Completed/Underpaid
 * from verified provider data (signed callback or authenticated API query).
 */
enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Processing = 'processing';
    case Confirming = 'confirming';
    case Completed = 'completed';
    // Provider finalised the invoice with less than the requested amount (funds received).
    case Underpaid = 'underpaid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    // Funds locked by the provider (e.g. Cryptomus AML lock).
    case OnHold = 'on_hold';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::OnHold => 'On hold',
            self::RefundPending => 'Refund pending',
            default => ucfirst($this->value),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Underpaid, self::OnHold, self::RefundPending => 'warning',
            self::Failed => 'danger',
            self::Expired, self::Cancelled => 'secondary',
            self::Refunded => 'dark',
            default => 'info',
        };
    }

    /** States in which funds have been received and credited to the merchant ledger. */
    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Underpaid, self::RefundPending, self::Refunded], true);
    }

    /** States that still wait for the customer or the blockchain. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Created, self::Pending, self::Processing, self::Confirming, self::OnHold], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isOpen() && $this !== self::RefundPending;
    }

    /**
     * Allowed transitions. Terminal "unpaid" states may still move to a paid
     * state because verified provider data always wins (late payments are
     * flagged for review by PaymentService).
     */
    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return false;
        }

        $paid = [self::Completed, self::Underpaid];

        return match ($this) {
            self::Created, self::Pending => $to !== self::Created && ! in_array($to, [self::RefundPending, self::Refunded], true),
            self::Processing => ! in_array($to, [self::Created, self::Pending, self::RefundPending, self::Refunded], true),
            self::Confirming => in_array($to, [...$paid, self::Failed, self::OnHold, self::Expired, self::Cancelled], true),
            self::OnHold => in_array($to, [...$paid, self::Failed, self::Cancelled], true),
            self::Failed, self::Expired, self::Cancelled => in_array($to, $paid, true),
            self::Completed, self::Underpaid => in_array($to, [self::RefundPending, self::Refunded], true),
            self::RefundPending => in_array($to, [self::Refunded, self::Completed, self::Underpaid], true),
            self::Refunded => false,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}

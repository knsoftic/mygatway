<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return $this === self::UnderReview ? 'Under review' : ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Rejected, self::Failed => 'danger',
            self::Cancelled => 'secondary',
            self::UnderReview, self::Requested => 'warning',
            default => 'info',
        };
    }

    /** Funds are reserved (debited from available, held in reserved). */
    public function holdsFunds(): bool
    {
        return in_array($this, [self::Requested, self::UnderReview, self::Approved, self::Processing], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Failed, self::Cancelled], true);
    }

    public function canBeCancelledByMerchant(): bool
    {
        return in_array($this, [self::Requested, self::UnderReview], true);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Requested => in_array($to, [self::UnderReview, self::Approved, self::Rejected, self::Cancelled], true),
            self::UnderReview => in_array($to, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => in_array($to, [self::Processing, self::Failed, self::Cancelled], true),
            self::Processing => in_array($to, [self::Completed, self::Failed], true),
            default => false,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}

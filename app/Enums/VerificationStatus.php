<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not submitted',
            self::Submitted => 'Under review',
            self::Approved => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Submitted => 'info',
            self::Rejected => 'danger',
            self::Unverified => 'secondary',
        };
    }
}

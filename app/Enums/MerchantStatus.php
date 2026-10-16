<?php

namespace App\Enums;

enum MerchantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'info',
            self::Restricted => 'warning',
            self::Suspended => 'danger',
        };
    }

    /** Restricted merchants can still view data but cannot move money. */
    public function canTransact(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}

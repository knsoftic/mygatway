<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function badge(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Failed => 'danger',
            default => 'info',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}

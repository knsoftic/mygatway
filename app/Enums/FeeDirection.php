<?php

namespace App\Enums;

enum FeeDirection: string
{
    case PayIn = 'payin';
    case PayOut = 'payout';

    public function label(): string
    {
        return $this === self::PayIn ? 'Pay In' : 'Pay Out';
    }
}

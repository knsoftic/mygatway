<?php

namespace App\Enums;

/**
 * Test and live data are fully separated: API keys, payments, payouts,
 * ledger accounts and webhook endpoints all carry a mode.
 */
enum Mode: string
{
    case Test = 'test';
    case Live = 'live';

    public function label(): string
    {
        return $this === self::Live ? 'Live' : 'Test';
    }
}

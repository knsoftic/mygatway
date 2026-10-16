<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Opaque, non-sequential identifiers exposed to merchants (never auto-increment IDs).
 */
final class PublicId
{
    public static function make(string $prefix): string
    {
        return $prefix.'_'.strtolower((string) Str::ulid());
    }

    /**
     * Provider-safe reference derived from a public ID.
     * Binance Pay merchantTradeNo/requestId accept letters and digits only (max 32);
     * Cryptomus order_id accepts alpha_dash (max 32 for payouts).
     */
    public static function providerReference(string $publicId): string
    {
        return str_replace('_', '', $publicId);
    }
}

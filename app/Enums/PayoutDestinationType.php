<?php

namespace App\Enums;

/**
 * Cryptomus pays out to blockchain addresses.
 * Binance Pay pays out to Binance users (Binance ID, or email for whitelisted merchants).
 */
enum PayoutDestinationType: string
{
    case CryptoAddress = 'crypto_address';
    case BinanceId = 'binance_id';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::CryptoAddress => 'Blockchain wallet address',
            self::BinanceId => 'Binance ID',
            self::Email => 'Binance account email',
        };
    }
}

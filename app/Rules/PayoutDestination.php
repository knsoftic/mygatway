<?php

namespace App\Rules;

use App\Enums\PayoutDestinationType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Format checks per destination type. Network-specific address validity is
 * enforced by the provider (Cryptomus rejects invalid addresses).
 */
class PayoutDestination implements ValidationRule
{
    public function __construct(private readonly string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        $ok = match (PayoutDestinationType::tryFrom($this->type)) {
            PayoutDestinationType::CryptoAddress => (bool) preg_match('/^[A-Za-z0-9:_\-.]{20,128}$/', $value),
            PayoutDestinationType::BinanceId => (bool) preg_match('/^\d{5,19}$/', $value),
            PayoutDestinationType::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            null => true, // destination.type has its own validation error
        };

        if (! $ok) {
            $fail(match (PayoutDestinationType::tryFrom($this->type)) {
                PayoutDestinationType::CryptoAddress => 'The destination must be a valid blockchain wallet address.',
                PayoutDestinationType::BinanceId => 'The destination must be a numeric Binance ID.',
                default => 'The destination must be a valid email address.',
            });
        }
    }
}

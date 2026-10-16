<?php

namespace App\Integrations\Data;

use App\Enums\PayoutStatus;

/**
 * Normalised provider payout state. `status` is one of Processing, Completed or
 * Failed, or null when the provider response needs a follow-up status query.
 */
final readonly class ProviderPayoutData
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public string $providerStatus,
        public ?PayoutStatus $status,
        public ?string $providerPayoutId = null,
        public ?string $txHash = null,
        public ?string $providerFee = null,
        public ?string $failureReason = null,
        public bool $isFinal = false,
        public array $raw = [],
    ) {}
}

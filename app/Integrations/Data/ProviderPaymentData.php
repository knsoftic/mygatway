<?php

namespace App\Integrations\Data;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Normalised view of a provider payment. `status` is null when the provider
 * status carries no state change for us (e.g. a failed refund attempt).
 */
final readonly class ProviderPaymentData
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public string $providerStatus,
        public ?PaymentStatus $status,
        public ?string $providerPaymentId = null,
        public ?string $checkoutUrl = null,
        public ?string $payAddress = null,
        public ?string $network = null,
        public ?string $receivedAmount = null,
        public ?string $receivedCurrency = null,
        public ?string $providerFee = null,
        public ?string $txHash = null,
        public ?string $payerAddress = null,
        public ?CarbonImmutable $expiresAt = null,
        public bool $isFinal = false,
        public array $raw = [],
    ) {}
}

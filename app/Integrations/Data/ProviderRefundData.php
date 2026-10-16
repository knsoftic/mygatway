<?php

namespace App\Integrations\Data;

use App\Enums\RefundStatus;

final readonly class ProviderRefundData
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public string $providerStatus,
        public RefundStatus $status,
        public ?string $providerRefundId = null,
        public array $raw = [],
    ) {}
}

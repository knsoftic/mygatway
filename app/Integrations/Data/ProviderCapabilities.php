<?php

namespace App\Integrations\Data;

use App\Enums\PayoutDestinationType;

/**
 * What a provider actually supports, according to its official documentation.
 */
final readonly class ProviderCapabilities
{
    /**
     * @param  list<PayoutDestinationType>  $payoutDestinationTypes
     * @param  list<string>  $notes
     */
    public function __construct(
        public bool $payIn,
        public bool $payOut,
        public bool $refund,
        public bool $partialRefund,
        public bool $webhooks,
        public bool $statusQuery,
        public bool $providerSandbox,
        public array $payoutDestinationTypes = [],
        public bool $payoutRequiresNetwork = false,
        public array $notes = [],
    ) {}

    public function supportsDestination(PayoutDestinationType $type): bool
    {
        return in_array($type, $this->payoutDestinationTypes, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pay_in' => $this->payIn,
            'pay_out' => $this->payOut,
            'refund' => $this->refund,
            'partial_refund' => $this->partialRefund,
            'webhooks' => $this->webhooks,
            'status_query' => $this->statusQuery,
            'provider_sandbox' => $this->providerSandbox,
            'payout_destination_types' => array_map(fn ($t) => $t->value, $this->payoutDestinationTypes),
            'notes' => $this->notes,
        ];
    }
}

<?php

namespace App\Integrations\Data;

/**
 * A verified, normalised inbound provider callback.
 */
final readonly class ProviderWebhookData
{
    public const KIND_PAYMENT = 'payment';

    public const KIND_PAYOUT = 'payout';

    public const KIND_UNKNOWN = 'unknown';

    /**
     * @param  string  $reference  Our reference sent to the provider (order_id / merchantTradeNo / requestId).
     * @param  array<string, mixed>  $acknowledgement  Body the provider expects in the HTTP 200 response.
     * @param  bool  $requiresStatusQuery  The callback alone is not enough to decide the final state.
     */
    public function __construct(
        public string $kind,
        public string $eventType,
        public ?string $reference,
        public ?string $externalId,
        public ?ProviderPaymentData $payment = null,
        public ?ProviderPayoutData $payout = null,
        public array $acknowledgement = ['status' => 'ok'],
        public bool $requiresStatusQuery = false,
    ) {}
}

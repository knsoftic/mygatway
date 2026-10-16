<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEventType;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PayoutResource;
use App\Jobs\DeliverWebhook;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates merchant webhook events (once per resource state) and queues a delivery
 * per subscribed endpoint. Deliveries run asynchronously with retries.
 */
class WebhookDispatcher
{
    public function paymentEvent(Payment $payment, WebhookEventType $type): ?WebhookEvent
    {
        $payment->loadMissing('provider');

        return $this->emit($payment->merchant_id, $payment->mode->value, $type, $payment,
            "payment:{$payment->id}:{$type->value}", (new PaymentResource($payment))->resolve());
    }

    public function payoutEvent(Payout $payout, WebhookEventType $type): ?WebhookEvent
    {
        $payout->loadMissing('provider');

        return $this->emit($payout->merchant_id, $payout->mode->value, $type, $payout,
            "payout:{$payout->id}:{$type->value}", (new PayoutResource($payout))->resolve());
    }

    /** @param  array<string, mixed>  $payload */
    public function emit(int $merchantId, string $mode, WebhookEventType $type, ?object $resource, string $dedupeKey, array $payload): ?WebhookEvent
    {
        try {
            $event = WebhookEvent::query()->create([
                'merchant_id' => $merchantId,
                'mode' => $mode,
                'type' => $type->value,
                'resource_type' => $resource?->getMorphClass(),
                'resource_id' => $resource?->getKey(),
                'payload' => $payload,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already emitted for this resource state: never notify twice.
            return null;
        }

        $endpoints = WebhookEndpoint::query()
            ->where('merchant_id', $merchantId)
            ->where('mode', $mode)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($type->value));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_event_id' => $event->id,
                'webhook_endpoint_id' => $endpoint->id,
                'status' => WebhookDelivery::PENDING,
                'next_attempt_at' => now(),
            ]);

            DB::afterCommit(fn () => DeliverWebhook::dispatch($delivery->id));
        }

        return $event;
    }
}

<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One delivery attempt of a merchant webhook. Retries are scheduled by
 * WebhookDeliveryService (next_attempt_at) and picked up by webhooks:dispatch-due.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookDeliveryService $service): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if ($delivery && in_array($delivery->status, [WebhookDelivery::PENDING, WebhookDelivery::RETRYING], true)) {
            $service->attempt($delivery);
        }
    }
}

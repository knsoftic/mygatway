<?php

namespace App\Services\Webhooks;

use App\Exceptions\GatewayException;
use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Performs a single delivery attempt and schedules the next retry on failure.
 * Retries follow config('gateway.webhooks.retry_schedule'); the scheduler's
 * webhooks:dispatch-due command picks up due retries, so no job sits in the
 * queue for hours.
 */
class WebhookDeliveryService
{
    public function __construct(private readonly HttpFactory $http, private readonly WebhookUrlGuard $guard) {}

    public function attempt(WebhookDelivery $delivery, ?int $triggeredBy = null): WebhookDelivery
    {
        // Claim the delivery atomically so two workers never send the same attempt.
        $claimed = DB::table('webhook_deliveries')
            ->where('id', $delivery->id)
            ->whereIn('status', [WebhookDelivery::PENDING, WebhookDelivery::RETRYING])
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['next_attempt_at' => now()->addMinutes(5), 'updated_at' => now()]);

        if ($claimed === 0) {
            return $delivery->refresh();
        }

        $delivery->load(['event', 'endpoint']);
        $endpoint = $delivery->endpoint;
        $event = $delivery->event;
        $attemptNo = $delivery->attempts + 1;

        $body = json_encode($event->envelope(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $status = null;
        $responseBody = null;
        $error = null;
        $start = microtime(true);

        try {
            if (! $endpoint->is_active) {
                throw new GatewayException('endpoint_disabled', 'Endpoint is disabled.');
            }

            $this->guard->assertAllowed($endpoint->url, $endpoint->mode);

            $response = $this->http
                ->withHeaders([
                    'User-Agent' => config('app.name').'-Webhooks/1.0',
                    'X-Gateway-Event' => $event->type,
                    'X-Gateway-Event-Id' => $event->public_id,
                    'X-Gateway-Timestamp' => (string) $timestamp,
                    config('gateway.webhooks.signature_header') => WebhookSigner::sign($body, $endpoint->secret, $timestamp),
                ])
                ->withOptions(['allow_redirects' => false])
                ->timeout((int) config('gateway.webhooks.timeout', 10))
                ->connectTimeout(5)
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $status = $response->status();
            $responseBody = mb_substr($response->body(), 0, 2000);
            if (! $response->successful()) {
                $error = "HTTP {$status}";
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
        }

        $duration = (int) ((microtime(true) - $start) * 1000);
        $succeeded = $error === null;

        WebhookDeliveryAttempt::query()->create([
            'webhook_delivery_id' => $delivery->id,
            'attempt' => $attemptNo,
            'response_status' => $status,
            'response_body' => $responseBody,
            'error' => $error,
            'duration_ms' => $duration,
            'triggered_by' => $triggeredBy,
            'created_at' => now(),
        ]);

        $schedule = config('gateway.webhooks.retry_schedule', []);
        $exhausted = $attemptNo > count($schedule);

        $delivery->forceFill([
            'attempts' => $attemptNo,
            'last_attempt_at' => now(),
            'response_status' => $status,
            'last_error' => $error,
            'status' => $succeeded ? WebhookDelivery::SUCCEEDED : ($exhausted ? WebhookDelivery::FAILED : WebhookDelivery::RETRYING),
            'next_attempt_at' => $succeeded || $exhausted ? null : now()->addSeconds((int) $schedule[$attemptNo - 1]),
            'delivered_at' => $succeeded ? now() : null,
        ])->save();

        $endpoint->forceFill($succeeded
            ? ['last_success_at' => now(), 'consecutive_failures' => 0]
            : ['last_failure_at' => now(), 'consecutive_failures' => $endpoint->consecutive_failures + 1]
        )->save();

        return $delivery;
    }

    /** Manual retry by an authorised user: re-arms a failed/retrying delivery immediately. */
    public function retryNow(WebhookDelivery $delivery, int $userId): WebhookDelivery
    {
        if ($delivery->status === WebhookDelivery::SUCCEEDED) {
            return $delivery;
        }

        $delivery->forceFill(['status' => WebhookDelivery::RETRYING, 'next_attempt_at' => now()])->save();

        return $this->attempt($delivery, $userId);
    }
}

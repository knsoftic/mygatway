<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Integrations\Exceptions\InvalidWebhookException;
use App\Integrations\GatewayManager;
use App\Jobs\ProcessProviderWebhook;
use App\Models\PaymentProvider;
use App\Models\ProviderWebhookLog;
use App\Services\Risk\RiskMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound provider callbacks: POST /api/webhooks/{cryptomus|binance_pay}
 *
 * 1. Verify authenticity synchronously (signature, and source IP for Cryptomus).
 * 2. Store the raw callback, drop exact duplicates.
 * 3. Queue processing; respond with the acknowledgement the provider expects.
 * Nothing is marked paid here - ProcessProviderWebhook confirms and applies the state.
 */
class ProviderWebhookController extends Controller
{
    // Signatures are not secrets; keeping them lets an admin re-verify and reprocess a stored callback.
    private const LOGGED_HEADERS = ['content-type', 'user-agent', 'binancepay-timestamp', 'binancepay-nonce', 'binancepay-certificate-sn', 'binancepay-signature', 'x-forwarded-for'];

    public function __invoke(Request $request, string $provider, GatewayManager $gateways, RiskMonitor $risk): JsonResponse
    {
        $model = PaymentProvider::findByCode($provider);
        if (! $model || ! $gateways->has($provider)) {
            return response()->json(['error' => 'unknown provider'], 404);
        }

        $raw = $request->getContent();
        $hash = hash('sha256', $raw);
        $headers = collect($request->headers->all())
            ->only(self::LOGGED_HEADERS)
            ->map(fn ($v) => is_array($v) ? implode(', ', $v) : $v)->all();

        try {
            $webhook = $gateways->live($provider)->parseWebhook($request);
        } catch (InvalidWebhookException $e) {
            ProviderWebhookLog::query()->create([
                'provider_id' => $model->id,
                'payload_hash' => $hash,
                'headers' => $headers,
                'raw_body' => mb_substr($raw, 0, 65535),
                'ip_address' => $request->ip(),
                'signature_valid' => false,
                'status' => ProviderWebhookLog::REJECTED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::warning('Rejected provider webhook', ['provider' => $provider, 'reason' => $e->getMessage(), 'ip' => $request->ip()]);

            $recentRejects = ProviderWebhookLog::query()->where('provider_id', $model->id)
                ->where('status', ProviderWebhookLog::REJECTED)->where('created_at', '>=', now()->subMinutes(10))->count();
            if ($recentRejects === 10) {
                $risk->record('webhook.invalid_signature_burst', 'high', ['provider' => $provider, 'ip' => $request->ip()]);
            }

            return response()->json(['error' => 'webhook rejected'], $e->httpStatus);
        }

        $duplicate = ProviderWebhookLog::query()
            ->where('provider_id', $model->id)
            ->where('payload_hash', $hash)
            ->whereIn('status', [ProviderWebhookLog::RECEIVED, ProviderWebhookLog::PROCESSED, ProviderWebhookLog::IGNORED])
            ->exists();

        $log = ProviderWebhookLog::query()->create([
            'provider_id' => $model->id,
            'event_type' => mb_substr($webhook->eventType, 0, 40),
            'external_id' => $webhook->externalId ? mb_substr($webhook->externalId, 0, 100) : null,
            'payload_hash' => $hash,
            'headers' => $headers,
            'raw_body' => $raw,
            'ip_address' => $request->ip(),
            'signature_valid' => true,
            'status' => $duplicate ? ProviderWebhookLog::DUPLICATE : ProviderWebhookLog::RECEIVED,
        ]);

        if (! $duplicate) {
            ProcessProviderWebhook::dispatch($log->id, $provider, $webhook);
        }

        return response()->json($webhook->acknowledgement);
    }
}

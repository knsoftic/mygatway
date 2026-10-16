<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProviderWebhook;
use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\ProviderWebhookLog;
use App\Models\RiskEvent;
use App\Models\WebhookDelivery;
use App\Services\Audit\AuditLogger;
use App\Services\Webhooks\WebhookDeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Webhook logs (inbound provider callbacks and outbound merchant deliveries),
 * audit trail, API request logs and risk events.
 */
class LogController extends Controller
{
    public function webhooks(Request $request): View
    {
        $f = $request->validate([
            'tab' => ['nullable', 'in:inbound,outbound'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $tab = $f['tab'] ?? 'inbound';

        return view('admin.logs.webhooks', [
            'tab' => $tab,
            'filters' => $f,
            'inbound' => $tab === 'inbound'
                ? ProviderWebhookLog::query()->with('provider')->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(30)->withQueryString()
                : null,
            'outbound' => $tab === 'outbound'
                ? WebhookDelivery::query()->with(['event.merchant', 'endpoint'])->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(30)->withQueryString()
                : null,
        ]);
    }

    public function showInbound(ProviderWebhookLog $log): View
    {
        return view('admin.logs.inbound-show', ['log' => $log->load('provider')]);
    }

    /** Re-queue a stored, signature-verified provider callback whose processing failed. */
    public function reprocessInbound(ProviderWebhookLog $log, AuditLogger $audit): RedirectResponse
    {
        abort_unless($log->signature_valid && $log->status === ProviderWebhookLog::FAILED, 409, 'Only verified callbacks that failed processing can be reprocessed.');

        $gateway = app(\App\Integrations\GatewayManager::class)->live($log->provider->code);
        $request = \Illuminate\Http\Request::create('/', 'POST', server: array_merge(
            ['REMOTE_ADDR' => $log->ip_address, 'CONTENT_TYPE' => 'application/json'],
            collect($log->headers ?? [])->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(),
        ), content: $log->raw_body);

        try {
            $webhook = $gateway->parseWebhook($request);
        } catch (\Throwable $e) {
            return back()->with('error', 'Callback could not be re-verified: '.$e->getMessage());
        }

        ProcessProviderWebhook::dispatch($log->id, $log->provider->code, $webhook);
        $audit->log('provider_webhook.reprocessed', $log);

        return back()->with('success', 'Callback re-verified and queued for processing.');
    }

    public function retryDelivery(Request $request, WebhookDelivery $delivery, WebhookDeliveryService $service, AuditLogger $audit): RedirectResponse
    {
        $service->retryNow($delivery, $request->user()->id);
        $audit->log('webhook_delivery.retried', $delivery);

        return back()->with('success', 'Delivery retried.');
    }

    public function audit(Request $request): View
    {
        $f = $request->validate([
            'tab' => ['nullable', 'in:audit,api'],
            'action' => ['nullable', 'string', 'max:80'],
            'merchant' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:error,success'],
        ]);
        $tab = $f['tab'] ?? 'audit';

        return view('admin.logs.audit', [
            'tab' => $tab,
            'filters' => $f,
            'audit' => $tab === 'audit'
                ? AuditLog::query()->with(['user', 'merchant'])
                    ->when($f['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                    ->when($f['merchant'] ?? null, fn ($q, $m) => $q->where('merchant_id', $m))
                    ->latest('id')->paginate(40)->withQueryString()
                : null,
            'api' => $tab === 'api'
                ? ApiRequestLog::query()->with(['merchant', 'apiKey'])
                    ->when($f['merchant'] ?? null, fn ($q, $m) => $q->where('merchant_id', $m))
                    ->when(($f['status'] ?? null) === 'error', fn ($q) => $q->where('status_code', '>=', 400))
                    ->when(($f['status'] ?? null) === 'success', fn ($q) => $q->where('status_code', '<', 400))
                    ->latest('id')->paginate(50)->withQueryString()
                : null,
        ]);
    }

    public function risk(Request $request): View
    {
        $open = $request->input('state', 'open') === 'open';

        return view('admin.logs.risk', [
            'open' => $open,
            'events' => RiskEvent::query()->with(['merchant', 'user'])
                ->when($open, fn ($q) => $q->whereNull('resolved_at'), fn ($q) => $q->whereNotNull('resolved_at'))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function resolveRisk(Request $request, RiskEvent $event, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $event->forceFill(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution_note' => $v['note']])->save();
        $audit->log('risk_event.resolved', $event, context: ['note' => $v['note']], merchantId: $event->merchant_id);

        return back()->with('success', 'Risk event resolved.');
    }
}

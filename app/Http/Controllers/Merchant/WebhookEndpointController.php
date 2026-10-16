<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\WebhookEventType;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Audit\AuditLogger;
use App\Services\Webhooks\WebhookDeliveryService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    private const MAX_ENDPOINTS_PER_MODE = 5;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $mode = $request->attributes->get('dashboard_mode');
        $merchantId = $request->user()->merchant_id;
        $endpoints = WebhookEndpoint::query()->where('merchant_id', $merchantId)->where('mode', $mode->value)->latest('id')->get();

        return view('merchant.webhooks.index', [
            'endpoints' => $endpoints,
            'eventTypes' => WebhookEventType::values(),
            'deliveries' => WebhookDelivery::query()->with(['event', 'endpoint'])
                ->whereIn('webhook_endpoint_id', $endpoints->pluck('id'))
                ->latest('id')->paginate(25),
            'newSecret' => $request->session()->get('new_webhook_secret'),
            'signatureHeader' => config('gateway.webhooks.signature_header'),
        ]);
    }

    public function store(Request $request, WebhookUrlGuard $guard): RedirectResponse
    {
        $mode = $request->attributes->get('dashboard_mode');
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:500'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(WebhookEventType::values())],
        ]);

        $count = WebhookEndpoint::query()->where('merchant_id', $request->user()->merchant_id)->where('mode', $mode->value)->count();
        if ($count >= self::MAX_ENDPOINTS_PER_MODE) {
            return back()->with('error', 'You can configure up to '.self::MAX_ENDPOINTS_PER_MODE.' endpoints per mode.');
        }

        $guard->assertAllowed($data['url'], $mode);

        $secret = 'whsec_'.Str::random(40);
        $endpoint = WebhookEndpoint::query()->create([
            'merchant_id' => $request->user()->merchant_id,
            'mode' => $mode->value,
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'secret' => $secret,
            'events' => $data['events'] ?? null,
            'is_active' => true,
        ]);
        $this->audit->log('webhook_endpoint.created', $endpoint, new: ['url' => $endpoint->url, 'mode' => $mode->value, 'events' => $endpoint->events]);

        return redirect()->route('merchant.webhooks.index')
            ->with('new_webhook_secret', ['endpoint' => $endpoint->public_id, 'secret' => $secret])
            ->with('success', 'Endpoint added. Copy the signing secret now - it is shown only once.');
    }

    public function update(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('manage', $endpoint);
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(WebhookEventType::values())],
        ]);

        $endpoint->forceFill(['is_active' => $data['is_active'], 'events' => $data['events'] ?? $endpoint->events])->save();
        $this->audit->logChanges('webhook_endpoint.updated', $endpoint);

        return back()->with('success', 'Endpoint updated.');
    }

    public function rollSecret(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('manage', $endpoint);
        $secret = 'whsec_'.Str::random(40);
        $endpoint->forceFill(['secret' => $secret])->save();
        $this->audit->log('webhook_endpoint.secret_rolled', $endpoint);

        return back()->with('new_webhook_secret', ['endpoint' => $endpoint->public_id, 'secret' => $secret])
            ->with('success', 'Signing secret rolled. Update your server with the new secret.');
    }

    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('manage', $endpoint);
        $this->audit->log('webhook_endpoint.deleted', $endpoint, old: ['url' => $endpoint->url]);
        $endpoint->delete();

        return back()->with('success', 'Endpoint removed.');
    }

    public function ping(WebhookEndpoint $endpoint, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('manage', $endpoint);

        $dispatcher->emit($endpoint->merchant_id, $endpoint->mode->value, WebhookEventType::Ping, null,
            'ping:'.$endpoint->id.':'.Str::uuid(), ['message' => 'Test event from '.config('app.name'), 'endpoint' => $endpoint->public_id]);

        return back()->with('success', 'Test event queued. Check the delivery log below in a few seconds.');
    }

    public function retry(Request $request, WebhookDelivery $delivery, WebhookDeliveryService $service): RedirectResponse
    {
        $delivery->load('endpoint');
        $this->authorize('manage', $delivery->endpoint);

        $service->retryNow($delivery, $request->user()->id);

        return back()->with('success', 'Delivery retried.');
    }
}

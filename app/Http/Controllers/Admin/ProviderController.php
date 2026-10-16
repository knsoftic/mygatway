<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Cryptomus\CryptomusGateway;
use App\Integrations\GatewayManager;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Provider toggles, API credentials and currency configuration.
 * Saved credentials are encrypted at rest and never sent back to the browser in full.
 */
class ProviderController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly GatewayManager $gateways) {}

    /**
     * Save provider API credentials. Requires the admin's current password; blank
     * inputs keep the current value. The audit log records field names only.
     */
    public function updateCredentials(Request $request, PaymentProvider $provider, ProviderCredentials $credentials): RedirectResponse
    {
        $fields = array_keys(ProviderCredentials::FIELDS[$provider->code] ?? []);
        abort_if($fields === [], 404);

        // A named error bag per provider, so errors show only under the form that was submitted.
        $bag = 'credentials_'.$provider->code;
        $v = $request->validateWithBag($bag, [
            'credentials' => ['nullable', 'array:'.implode(',', $fields)],
            'credentials.*' => ['nullable', 'string', 'max:4096', 'not_regex:/\s/'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string', 'in:'.implode(',', $fields)],
            'current_password' => ['required', 'current_password'],
        ], [
            'credentials.*.not_regex' => 'Keys must not contain spaces or line breaks.',
            'current_password.current_password' => 'The password is incorrect.',
        ]);

        if (($wallet = $v['credentials']['payout_wallet'] ?? null) && ! in_array($wallet, ['FUNDING_WALLET', 'SPOT_WALLET', 'FUNDING_AND_SPOT_WALLET'], true)) {
            return back()->withErrors(['credentials.payout_wallet' => 'Use FUNDING_WALLET, SPOT_WALLET or FUNDING_AND_SPOT_WALLET.'], $bag);
        }

        $changed = $credentials->update($provider, $v['credentials'] ?? [], $v['remove'] ?? []);
        if ($changed === []) {
            return back()->with('warning', 'Nothing changed. Leave a field blank to keep its current value.');
        }

        $this->audit->log('provider.credentials_updated', $provider, context: ['fields' => $changed]);

        // Check the new credentials straight away so the admin sees whether they work.
        $result = $this->gateways->live($provider->code)->healthCheck();
        $provider->forceFill([
            'health_status' => $result->status,
            'health_message' => mb_substr($result->message, 0, 255),
            'health_checked_at' => now(),
        ])->save();

        $message = "{$provider->name} credentials saved. Connection check: {$result->status}".($result->message ? ' ('.rtrim($result->message, '.').')' : '').'.';

        return back()->with($result->status === 'healthy' ? 'success' : 'warning', $message);
    }

    public function index(ProviderCredentials $credentials): View
    {
        $providers = PaymentProvider::query()->withCount([
            'currencies as payin_count' => fn ($q) => $q->where('direction', 'payin')->usable(),
            'currencies as payout_count' => fn ($q) => $q->where('direction', 'payout')->usable(),
        ])->orderBy('priority')->get();

        return view('admin.providers.index', [
            'providers' => $providers->map(function (PaymentProvider $p) use ($credentials) {
                $gateway = $this->gateways->live($p->code);

                return [
                    'model' => $p,
                    'capabilities' => $gateway->capabilities(),
                    'configured' => $gateway->isConfigured(),
                    'payoutConfigured' => $gateway instanceof CryptomusGateway ? $gateway->payoutsConfigured() : $gateway->isConfigured(),
                    'webhookUrl' => route('webhooks.provider', ['provider' => $p->code]),
                    'credentials' => $credentials->status($p),
                ];
            }),
        ]);
    }

    public function update(Request $request, PaymentProvider $provider): RedirectResponse
    {
        $v = $request->validate(['priority' => ['required', 'integer', 'between:1,1000']]);
        $provider->forceFill([
            'payin_enabled' => $request->boolean('payin_enabled'),
            'payout_enabled' => $request->boolean('payout_enabled'),
            'refund_enabled' => $request->boolean('refund_enabled'),
            'priority' => $v['priority'],
        ])->save();
        $this->audit->logChanges('provider.updated', $provider);

        return back()->with('success', "{$provider->name} settings saved.");
    }

    public function currencies(PaymentProvider $provider): View
    {
        return view('admin.providers.currencies', [
            'provider' => $provider,
            'currencies' => SupportedCurrency::query()->where('provider_id', $provider->id)->orderBy('direction')->orderBy('currency')->orderBy('network')->get(),
        ]);
    }

    public function storeCurrency(Request $request, PaymentProvider $provider): RedirectResponse
    {
        $money = ['nullable', 'string', 'regex:/^\d{1,18}(\.\d{1,8})?$/'];
        $v = $request->validate([
            'direction' => ['required', 'in:payin,payout'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/'],
            'network' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{1,32}$/'],
            'min_amount' => $money,
            'max_amount' => $money,
        ]);

        $row = SupportedCurrency::query()->updateOrCreate(
            ['provider_id' => $provider->id, 'direction' => $v['direction'], 'currency' => strtoupper($v['currency']), 'network' => $v['network'] ?? ''],
            ['min_amount' => $v['min_amount'] ?: null, 'max_amount' => $v['max_amount'] ?: null, 'is_enabled' => true, 'is_available' => true, 'source' => 'admin'],
        );
        $this->audit->log('provider.currency_saved', $row, new: $row->only(['direction', 'currency', 'network', 'min_amount', 'max_amount']));

        return back()->with('success', 'Currency saved.');
    }

    public function toggleCurrency(SupportedCurrency $currency): RedirectResponse
    {
        $currency->forceFill(['is_enabled' => ! $currency->is_enabled])->save();
        $this->audit->log('provider.currency_toggled', $currency, new: ['is_enabled' => $currency->is_enabled]);

        return back()->with('success', 'Currency '.($currency->is_enabled ? 'enabled' : 'disabled').'.');
    }

    public function healthCheck(): RedirectResponse
    {
        Artisan::call('gateway:check-providers');

        return back()->with('success', 'Provider health checked.');
    }

    public function syncCurrencies(): RedirectResponse
    {
        Artisan::call('gateway:sync-currencies');
        $this->audit->log('provider.currencies_synced');

        return back()->with('success', trim(Artisan::output()) ?: 'Currencies synced.');
    }
}

<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\Mode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreApiKeyRequest;
use App\Models\ApiRequestLog;
use App\Models\MerchantApiKey;
use App\Services\Merchants\ApiKeyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function index(Request $request): View
    {
        $merchant = $request->user()->merchant;
        $keys = $merchant->apiKeys()->with('creator')->latest('id')->get();

        $usage = ApiRequestLog::query()
            ->where('merchant_id', $merchant->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('api_key_id, COUNT(*) AS total, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS errors')
            ->groupBy('api_key_id')->get()->keyBy('api_key_id');

        return view('merchant.api-keys.index', [
            'keys' => $keys,
            'usage' => $usage,
            'scopes' => config('gateway.api.scopes'),
            'canLive' => $merchant->isLiveEnabled() && $request->user()->hasTwoFactorEnabled(),
            'newSecret' => $request->session()->get('new_api_secret'),
        ]);
    }

    public function store(StoreApiKeyRequest $request): RedirectResponse
    {
        $v = $request->validated();

        [$key, $secret] = $this->keys->create(
            $request->user()->merchant,
            $v['name'],
            Mode::from($v['mode']),
            $v['scopes'],
            $request->ipList(),
            $v['rate_limit_per_minute'] ?? null,
            $request->user(),
        );

        // Flashed for exactly one page view; never stored in plain text.
        return redirect()->route('merchant.api-keys.index')
            ->with('new_api_secret', ['key_id' => $key->key_id, 'secret' => $secret, 'mode' => $key->mode->value])
            ->with('success', 'API key created. Copy the secret now - it will not be shown again.');
    }

    public function rotate(Request $request, MerchantApiKey $apiKey): RedirectResponse
    {
        $this->authorize('update', $apiKey);
        [$key, $secret] = $this->keys->rotate($apiKey, $request->user());

        return redirect()->route('merchant.api-keys.index')
            ->with('new_api_secret', ['key_id' => $key->key_id, 'secret' => $secret, 'mode' => $key->mode->value])
            ->with('success', 'Key rotated. The old secret no longer works. Copy the new secret now.');
    }

    public function destroy(Request $request, MerchantApiKey $apiKey): RedirectResponse
    {
        $this->authorize('update', $apiKey);
        $this->keys->revoke($apiKey, $request->user());

        return back()->with('success', "API key {$apiKey->key_id} revoked.");
    }
}

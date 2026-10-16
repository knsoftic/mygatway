<?php

namespace App\Services\Providers;

use App\Models\PaymentProvider;
use Throwable;

/**
 * Resolves provider API credentials.
 *
 * A value saved in the admin panel (encrypted in payment_providers.credentials)
 * wins; otherwise the environment variable from config/services.php is used.
 * Secret values are never returned to views, only their source and last 4 characters.
 */
class ProviderCredentials
{
    /**
     * Editable fields per provider: config key => [label, is secret, env variable].
     *
     * @var array<string, array<string, array{0: string, 1: bool, 2: string}>>
     */
    public const FIELDS = [
        PaymentProvider::CRYPTOMUS => [
            'merchant_id' => ['Merchant UUID', false, 'CRYPTOMUS_MERCHANT_ID'],
            'payment_key' => ['Payment API key', true, 'CRYPTOMUS_PAYMENT_KEY'],
            'payout_key' => ['Payout API key', true, 'CRYPTOMUS_PAYOUT_KEY'],
        ],
        PaymentProvider::BINANCE_PAY => [
            'api_key' => ['API key (Certificate SN)', true, 'BINANCE_PAY_API_KEY'],
            'secret_key' => ['Secret key', true, 'BINANCE_PAY_SECRET_KEY'],
            'payout_wallet' => ['Payout wallet (FUNDING_WALLET, SPOT_WALLET or FUNDING_AND_SPOT_WALLET)', false, 'BINANCE_PAY_PAYOUT_WALLET'],
        ],
    ];

    /** @return array<string, mixed> config('services.<code>') with admin-saved values applied. */
    public function config(string $code): array
    {
        return array_merge((array) config("services.{$code}", []), $this->stored($code));
    }

    /**
     * Status of each field for the admin page.
     *
     * @return list<array{key: string, label: string, secret: bool, env: string, source: string, hint: ?string}>
     */
    public function status(PaymentProvider $provider): array
    {
        $stored = array_filter((array) ($provider->credentials ?? []), 'filled');
        $env = (array) config("services.{$provider->code}", []);
        $rows = [];

        foreach (self::FIELDS[$provider->code] ?? [] as $key => [$label, $secret, $envName]) {
            $value = $stored[$key] ?? null;
            $source = $value !== null ? 'admin' : (filled($env[$key] ?? null) ? 'env' : 'none');
            $value ??= $env[$key] ?? null;

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'secret' => $secret,
                'env' => $envName,
                'source' => $source,
                'hint' => filled($value) ? ($secret ? '••••'.substr((string) $value, -4) : (string) $value) : null,
            ];
        }

        return $rows;
    }

    /**
     * Applies an admin update: filled inputs replace values, listed keys are removed,
     * blank inputs keep the current value.
     *
     * @param  array<string, ?string>  $input
     * @param  list<string>  $remove
     * @return list<string> Names of the fields that changed (never their values).
     */
    public function update(PaymentProvider $provider, array $input, array $remove): array
    {
        $allowed = array_keys(self::FIELDS[$provider->code] ?? []);
        $current = array_filter((array) ($provider->credentials ?? []), 'filled');
        $changed = [];

        foreach ($allowed as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '' && ($current[$key] ?? null) !== $value) {
                $current[$key] = $value;
                $changed[] = $key;
            } elseif ($value === '' && in_array($key, $remove, true) && isset($current[$key])) {
                unset($current[$key]);
                $changed[] = $key;
            }
        }

        if ($changed !== []) {
            $provider->forceFill([
                'credentials' => $current === [] ? null : $current,
                'credentials_updated_at' => now(),
            ])->save();
        }

        return $changed;
    }

    /** @return array<string, string> */
    private function stored(string $code): array
    {
        try {
            $provider = PaymentProvider::query()->where('code', $code)->first(['id', 'code', 'credentials']);
        } catch (Throwable) {
            // Database not migrated or unreachable (e.g. during install): fall back to env.
            return [];
        }

        return array_filter((array) ($provider?->credentials ?? []), 'filled');
    }
}

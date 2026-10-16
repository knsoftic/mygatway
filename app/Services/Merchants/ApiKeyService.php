<?php

namespace App\Services\Merchants;

use App\Enums\Mode;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Risk\RiskMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Merchant API credentials.
 *
 * The secret (sk_live_... / sk_test_...) is shown exactly once at creation. Only an
 * HMAC-SHA256 of it, keyed with a server-side pepper, is stored. Secrets have 288
 * bits of entropy, so a keyed hash is sufficient and keeps per-request checks fast.
 */
class ApiKeyService
{
    public function __construct(private readonly AuditLogger $audit, private readonly RiskMonitor $risk) {}

    /**
     * @param  list<string>  $scopes
     * @param  list<string>|null  $ipAllowlist
     * @return array{0: MerchantApiKey, 1: string} The key and its one-time plain secret.
     */
    public function create(
        Merchant $merchant,
        string $name,
        Mode $mode,
        array $scopes,
        ?array $ipAllowlist,
        ?int $rateLimit,
        ?User $creator,
        ?int $rotatedFromId = null,
    ): array {
        $secret = 'sk_'.$mode->value.'_'.Str::random(48);

        $key = MerchantApiKey::query()->create([
            'merchant_id' => $merchant->id,
            'name' => $name,
            'mode' => $mode->value,
            'key_id' => 'key_'.$mode->value.'_'.Str::lower(Str::random(20)),
            'secret_hash' => $this->hash($secret),
            'secret_last4' => substr($secret, -4),
            'scopes' => array_values(array_intersect(config('gateway.api.scopes'), $scopes)),
            'ip_allowlist' => $ipAllowlist ?: null,
            'rate_limit_per_minute' => min(
                $rateLimit ?: (int) config('gateway.api.default_rate_limit_per_minute'),
                (int) config('gateway.api.max_rate_limit_per_minute'),
            ),
            'created_by' => $creator?->id,
            'rotated_from_id' => $rotatedFromId,
        ]);

        $this->audit->log('api_key.created', $key, new: [
            'name' => $key->name, 'mode' => $mode->value, 'key_id' => $key->key_id, 'scopes' => $key->scopes,
            'ip_allowlist' => $key->ip_allowlist,
        ], user: $creator, merchantId: $merchant->id);

        if ($mode === Mode::Live) {
            $this->risk->record('api_key.live_created', 'low', ['key_id' => $key->key_id], $merchant->id, $creator?->id, $key);
        }

        return [$key, $secret];
    }

    /**
     * Issue a replacement key with the same settings and revoke the old one.
     *
     * @return array{0: MerchantApiKey, 1: string}
     */
    public function rotate(MerchantApiKey $key, ?User $actor): array
    {
        return DB::transaction(function () use ($key, $actor) {
            [$new, $secret] = $this->create(
                $key->merchant, $key->name, $key->mode, $key->scopes ?? [], $key->ip_allowlist,
                $key->rate_limit_per_minute, $actor, $key->id,
            );
            $this->revoke($key, $actor, 'rotated');

            return [$new, $secret];
        });
    }

    public function revoke(MerchantApiKey $key, ?User $actor, string $reason = 'revoked'): void
    {
        if ($key->revoked_at !== null) {
            return;
        }

        $key->forceFill(['revoked_at' => now(), 'revoked_by' => $actor?->id])->save();
        $this->audit->log('api_key.revoked', $key, context: ['reason' => $reason, 'key_id' => $key->key_id], user: $actor, merchantId: $key->merchant_id);
    }

    public function findActiveBySecret(string $secret): ?MerchantApiKey
    {
        if (! preg_match('/^sk_(test|live)_[A-Za-z0-9]{48}$/', $secret)) {
            return null;
        }

        $key = MerchantApiKey::query()->with('merchant')->where('secret_hash', $this->hash($secret))->first();

        return $key && $key->isActive() ? $key : null;
    }

    public function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->pepper());
    }

    private function pepper(): string
    {
        $pepper = (string) config('gateway.api.key_pepper');
        if ($pepper === '') {
            if (app()->isProduction()) {
                throw new RuntimeException('GATEWAY_API_KEY_PEPPER must be set in production.');
            }
            $pepper = (string) config('app.key');
        }

        return str_starts_with($pepper, 'base64:') ? base64_decode(substr($pepper, 7)) : $pepper;
    }
}

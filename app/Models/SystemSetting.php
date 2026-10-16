<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'updated_by'])]
class SystemSetting extends Model
{
    /**
     * Editable settings and their defaults. Anything security-critical
     * (credentials, peppers) stays in the environment instead.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'platform.support_email' => 'support@example.com',
        'payouts.manual_approval' => true,
        'payouts.pass_network_fee_to_merchant' => true,
        'merchants.auto_activate_on_verification' => true,
        'maintenance.block_new_payments' => false,
        'maintenance.block_new_payouts' => false,
    ];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('system_settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        Cache::forget('system_settings');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provider configuration and admin toggles.
 *
 * API credentials can be saved from the admin panel; they are stored encrypted
 * (APP_KEY) in "credentials", are never mass assignable or serialised, and
 * override the matching environment variables (see ProviderCredentials).
 */
#[Fillable([
    'code', 'name', 'payin_enabled', 'payout_enabled', 'refund_enabled', 'priority', 'settings',
    'health_status', 'health_message', 'health_checked_at',
])]
class PaymentProvider extends Model
{
    public const CRYPTOMUS = 'cryptomus';

    public const BINANCE_PAY = 'binance_pay';

    /** @var list<string> */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'payin_enabled' => 'boolean',
            'payout_enabled' => 'boolean',
            'refund_enabled' => 'boolean',
            'settings' => 'array',
            'credentials' => 'encrypted:array',
            'credentials_updated_at' => 'datetime',
            'health_checked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(SupportedCurrency::class, 'provider_id');
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}

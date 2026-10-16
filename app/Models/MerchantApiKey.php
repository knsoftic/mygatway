<?php

namespace App\Models;

use App\Enums\Mode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'merchant_id', 'name', 'mode', 'key_id', 'secret_hash', 'secret_last4', 'scopes', 'ip_allowlist',
    'rate_limit_per_minute', 'expires_at', 'created_by', 'rotated_from_id',
])]
#[Hidden(['secret_hash'])]
class MerchantApiKey extends Model
{
    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'scopes' => 'array',
            'ip_allowlist' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key_id';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function allowsIp(?string $ip): bool
    {
        $list = $this->ip_allowlist ?? [];
        if ($list === []) {
            return true;
        }

        return $ip !== null && \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $list);
    }

    public function maskedSecret(): string
    {
        return 'sk_'.$this->mode->value.'_••••••••'.$this->secret_last4;
    }
}

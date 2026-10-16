<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id', 'direction', 'currency', 'network', 'min_amount', 'max_amount', 'provider_fee_percent',
    'provider_fee_fixed', 'is_available', 'is_enabled', 'source', 'synced_at',
])]
class SupportedCurrency extends Model
{
    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:8',
            'max_amount' => 'decimal:8',
            'provider_fee_percent' => 'decimal:4',
            'provider_fee_fixed' => 'decimal:8',
            'is_available' => 'boolean',
            'is_enabled' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_available', true)->where('is_enabled', true);
    }

    public function networkLabel(): string
    {
        return $this->network === '' ? 'Default' : strtoupper($this->network);
    }
}

<?php

namespace App\Models;

use App\Enums\FeeDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'direction', 'merchant_id', 'provider_id', 'currency', 'percentage', 'fixed_amount', 'min_fee',
    'max_fee', 'effective_from', 'effective_until', 'is_active', 'created_by', 'updated_by',
])]
class FeeRule extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => FeeDirection::class,
            'percentage' => 'decimal:4',
            'fixed_amount' => 'decimal:8',
            'min_fee' => 'decimal:8',
            'max_fee' => 'decimal:8',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }

    /** Rules that are switched on and inside their effective window at the given moment. */
    public function scopeEffectiveAt(Builder $query, Carbon $at): Builder
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $at));
    }

    /**
     * Specificity score used to pick the most specific matching rule:
     * merchant (4) > currency (2) > provider (1).
     */
    public function specificity(): int
    {
        return ($this->merchant_id ? 4 : 0) + ($this->currency ? 2 : 0) + ($this->provider_id ? 1 : 0);
    }

    public function describe(): string
    {
        $parts = [];
        if (bccomp((string) $this->percentage, '0', 4) === 1) {
            $parts[] = rtrim(rtrim((string) $this->percentage, '0'), '.').'%';
        }
        if (bccomp((string) $this->fixed_amount, '0', 8) === 1) {
            $parts[] = \App\Support\Decimal::format($this->fixed_amount).' '.$this->currency;
        }

        return $parts === [] ? 'Free' : implode(' + ', $parts);
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'direction' => $this->direction->value,
            'merchant_id' => $this->merchant_id,
            'provider_id' => $this->provider_id,
            'currency' => $this->currency,
            'percentage' => (string) $this->percentage,
            'fixed_amount' => (string) $this->fixed_amount,
            'min_fee' => $this->min_fee,
            'max_fee' => $this->max_fee,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'effective_until' => $this->effective_until?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

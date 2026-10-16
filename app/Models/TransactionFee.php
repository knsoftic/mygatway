<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable record of a fee calculation. Never updated after insert.
 */
#[WithoutTimestamps]
#[Fillable([
    'feeable_type', 'feeable_id', 'kind', 'stage', 'fee_rule_id', 'currency', 'base_amount', 'percentage',
    'fixed_amount', 'min_fee', 'max_fee', 'amount', 'rule_snapshot', 'created_at',
])]
class TransactionFee extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Transaction fees are immutable.'));
        static::deleting(fn () => throw new LogicException('Transaction fees are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:8',
            'percentage' => 'decimal:4',
            'fixed_amount' => 'decimal:8',
            'min_fee' => 'decimal:8',
            'max_fee' => 'decimal:8',
            'amount' => 'decimal:8',
            'rule_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function feeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class, 'fee_rule_id');
    }
}

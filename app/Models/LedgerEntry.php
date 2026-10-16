<?php

namespace App\Models;

use App\Enums\EntryDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[WithoutTimestamps]
#[Fillable(['transaction_id', 'ledger_account_id', 'direction', 'amount', 'currency', 'balance_after', 'created_at'])]
class LedgerEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'amount' => 'decimal:8',
            'balance_after' => 'decimal:8',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}

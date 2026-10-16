<?php

namespace App\Models;

use App\Enums\EntryDirection;
use App\Enums\LedgerAccountType;
use App\Enums\Mode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `balance` column is a cache maintained exclusively by LedgerService in the
 * same database transaction as the entries; it is never edited directly.
 */
#[Fillable(['account_key', 'mode', 'currency', 'type', 'merchant_id', 'provider_id', 'normal_balance'])]
class LedgerAccount extends Model
{
    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'type' => LedgerAccountType::class,
            'normal_balance' => EntryDirection::class,
            'balance' => 'decimal:8',
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

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public static function keyFor(Mode $mode, string $currency, LedgerAccountType $type, ?int $merchantId, ?int $providerId): string
    {
        return implode(':', [
            $mode->value,
            strtoupper($currency),
            $type->value,
            $merchantId ? 'm'.$merchantId : '-',
            $providerId ? 'p'.$providerId : '-',
        ]);
    }
}

<?php

namespace App\Models;

use App\Enums\Mode;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Ledger journal: a balanced set of debit/credit entries for one business event.
 * Posted transactions are immutable; corrections are made with reversal transactions.
 */
#[Fillable([
    'merchant_id', 'mode', 'type', 'reference_type', 'reference_id', 'currency', 'description',
    'idempotency_key', 'created_by', 'posted_at',
])]
class Transaction extends Model
{
    public const PAYIN_SETTLEMENT = 'payin_settlement';

    public const PAYOUT_RESERVE = 'payout_reserve';

    public const PAYOUT_SETTLEMENT = 'payout_settlement';

    public const PAYOUT_RELEASE = 'payout_release';

    public const REFUND_RESERVE = 'refund_reserve';

    public const REFUND_SETTLEMENT = 'refund_settlement';

    public const REFUND_RELEASE = 'refund_release';

    public const REVERSAL = 'reversal';

    protected static function booted(): void
    {
        static::creating(function (Transaction $tx) {
            $tx->public_id ??= PublicId::make('txn');
        });
        static::updating(fn () => throw new LogicException('Posted ledger transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted ledger transactions are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'posted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::PAYIN_SETTLEMENT => 'Pay In settlement',
            self::PAYOUT_RESERVE => 'Payout reserved',
            self::PAYOUT_SETTLEMENT => 'Payout settled',
            self::PAYOUT_RELEASE => 'Payout reserve released',
            self::REFUND_RESERVE => 'Refund reserved',
            self::REFUND_SETTLEMENT => 'Refund settled',
            self::REFUND_RELEASE => 'Refund reserve released',
            self::REVERSAL => 'Reversal',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}

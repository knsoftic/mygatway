<?php

namespace App\Models;

use App\Enums\Mode;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'merchant_id', 'api_key_id', 'requested_by_user_id', 'mode', 'provider_id', 'merchant_reference',
    'description', 'amount', 'currency', 'network', 'destination_type', 'destination', 'destination_memo',
    'service_fee', 'network_fee', 'total_debit', 'status', 'metadata',
])]
class Payout extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Payout $payout) {
            $payout->public_id ??= PublicId::make('po');
            $payout->provider_order_id ??= PublicId::providerReference($payout->public_id);
        });
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'status' => PayoutStatus::class,
            'destination_type' => PayoutDestinationType::class,
            'amount' => 'decimal:8',
            'service_fee' => 'decimal:8',
            'network_fee' => 'decimal:8',
            'total_debit' => 'decimal:8',
            'provider_fee' => 'decimal:8',
            'metadata' => 'array',
            'needs_review' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PayoutApproval::class)->latest('id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fees(): MorphMany
    {
        return $this->morphMany(TransactionFee::class, 'feeable');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    /** Destination with the middle masked, for lists and notifications. */
    public function maskedDestination(): string
    {
        $d = $this->destination;
        if (strlen($d) <= 12) {
            return $d;
        }

        return substr($d, 0, 6).'…'.substr($d, -6);
    }
}

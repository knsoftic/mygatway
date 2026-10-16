<?php

namespace App\Models;

use App\Enums\Mode;
use App\Enums\RefundStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'merchant_id', 'mode', 'provider_id', 'amount', 'currency', 'address', 'reason', 'status',
    'requested_by',
])]
class Refund extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Refund $refund) {
            $refund->public_id ??= PublicId::make('rf');
            $refund->provider_request_id ??= PublicId::providerReference($refund->public_id);
        });
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'status' => RefundStatus::class,
            'amount' => 'decimal:8',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }
}

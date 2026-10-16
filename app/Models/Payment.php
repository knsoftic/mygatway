<?php

namespace App\Models;

use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'merchant_id', 'api_key_id', 'mode', 'provider_id', 'merchant_reference', 'description', 'amount',
    'currency', 'network', 'service_fee', 'status', 'provider_order_id', 'customer_email', 'success_url',
    'cancel_url', 'metadata', 'expires_at',
])]
class Payment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->public_id ??= PublicId::make('pay');
            $payment->provider_order_id ??= PublicId::providerReference($payment->public_id);
        });
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:8',
            'received_amount' => 'decimal:8',
            'service_fee' => 'decimal:8',
            'provider_fee' => 'decimal:8',
            'network_fee' => 'decimal:8',
            'merchant_net_amount' => 'decimal:8',
            'metadata' => 'array',
            'callback_verified' => 'boolean',
            'callback_verified_at' => 'datetime',
            'needs_review' => 'boolean',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(MerchantApiKey::class, 'api_key_id');
    }

    public function fees(): MorphMany
    {
        return $this->morphMany(TransactionFee::class, 'feeable');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    public function quotedServiceFee(): ?TransactionFee
    {
        return $this->fees->first(fn (TransactionFee $f) => $f->kind === 'service' && $f->stage === 'quote');
    }
}

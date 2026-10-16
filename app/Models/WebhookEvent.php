<?php

namespace App\Models;

use App\Enums\Mode;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[WithoutTimestamps]
#[Fillable(['merchant_id', 'mode', 'type', 'resource_type', 'resource_id', 'payload', 'dedupe_key', 'created_at'])]
class WebhookEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event) {
            $event->public_id ??= PublicId::make('evt');
            $event->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'payload' => 'array',
            'created_at' => 'datetime',
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

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /** The exact JSON document delivered to merchants. */
    public function envelope(): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type,
            'mode' => $this->mode->value,
            'created_at' => $this->created_at->toIso8601String(),
            'data' => $this->payload,
        ];
    }
}

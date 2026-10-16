<?php

namespace App\Models;

use App\Enums\Mode;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['merchant_id', 'mode', 'url', 'description', 'secret', 'events', 'is_active'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $endpoint) {
            $endpoint->public_id ??= PublicId::make('we');
        });
    }

    protected function casts(): array
    {
        return [
            'mode' => Mode::class,
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $eventType): bool
    {
        return $this->events === null || $this->events === [] || in_array($eventType, $this->events, true);
    }
}

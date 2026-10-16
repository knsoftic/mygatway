<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'webhook_event_id', 'webhook_endpoint_id', 'status', 'attempts', 'next_attempt_at', 'last_attempt_at',
    'response_status', 'last_error', 'delivered_at',
])]
class WebhookDelivery extends Model
{
    public const PENDING = 'pending';

    public const RETRYING = 'retrying';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function attemptLog(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class)->orderByDesc('id');
    }

    public function badge(): string
    {
        return match ($this->status) {
            self::SUCCEEDED => 'success',
            self::FAILED => 'danger',
            self::RETRYING => 'warning',
            default => 'secondary',
        };
    }
}

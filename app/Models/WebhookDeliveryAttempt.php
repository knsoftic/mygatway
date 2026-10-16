<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
#[Fillable([
    'webhook_delivery_id', 'attempt', 'response_status', 'response_body', 'error', 'duration_ms',
    'triggered_by', 'created_at',
])]
class WebhookDeliveryAttempt extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'webhook_delivery_id');
    }
}

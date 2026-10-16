<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
#[Fillable([
    'request_id', 'merchant_id', 'api_key_id', 'mode', 'method', 'path', 'status_code', 'error_code',
    'ip_address', 'user_agent', 'idempotency_key', 'duration_ms', 'created_at',
])]
class ApiRequestLog extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(MerchantApiKey::class, 'api_key_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id', 'event_type', 'external_id', 'payload_hash', 'headers', 'raw_body', 'ip_address',
    'signature_valid', 'status', 'error', 'attempts', 'processed_at',
])]
class ProviderWebhookLog extends Model
{
    public const RECEIVED = 'received';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const FAILED = 'failed';

    public const REJECTED = 'rejected';

    public const DUPLICATE = 'duplicate';

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }

    public function badge(): string
    {
        return match ($this->status) {
            self::PROCESSED => 'success',
            self::REJECTED, self::FAILED => 'danger',
            self::DUPLICATE, self::IGNORED => 'secondary',
            default => 'info',
        };
    }
}

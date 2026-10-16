<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'merchant_id', 'user_id', 'type', 'severity', 'details', 'subject_type', 'subject_id', 'resolved_at',
    'resolved_by', 'resolution_note',
])]
class RiskEvent extends Model
{
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function badge(): string
    {
        return match ($this->severity) {
            'high' => 'danger',
            'medium' => 'warning',
            default => 'secondary',
        };
    }
}

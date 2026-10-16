<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'merchant_id', 'mode', 'key', 'request_method', 'request_path', 'request_hash', 'response_status',
    'response_body', 'completed_at', 'expires_at',
])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'reconciliation_run_id', 'subject_type', 'subject_id', 'issue', 'details', 'status', 'resolved_by',
    'resolved_at',
])]
class ReconciliationItem extends Model
{
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

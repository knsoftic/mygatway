<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'company', 'subject', 'message', 'ip_address', 'handled_at'])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}

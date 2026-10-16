<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'merchant_id', 'legal_name', 'registration_number', 'country', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'contact_name', 'contact_phone', 'support_email', 'business_description',
])]
class MerchantProfile extends Model
{
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}

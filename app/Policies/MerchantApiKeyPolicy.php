<?php

namespace App\Policies;

use App\Enums\MerchantStatus;
use App\Models\MerchantApiKey;
use App\Models\User;

class MerchantApiKeyPolicy
{
    public function create(User $user): bool
    {
        return $user->merchant !== null && $user->merchant->status !== MerchantStatus::Suspended;
    }

    /** Rotate or revoke: only keys of the user's own merchant. */
    public function update(User $user, MerchantApiKey $key): bool
    {
        return $user->merchant_id !== null && $user->merchant_id === $key->merchant_id && $key->revoked_at === null;
    }
}

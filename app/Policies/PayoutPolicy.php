<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    public function view(User $user, Payout $payout): bool
    {
        return $user->merchant_id !== null && $user->merchant_id === $payout->merchant_id;
    }

    public function cancel(User $user, Payout $payout): bool
    {
        return $this->view($user, $payout) && $payout->status->canBeCancelledByMerchant();
    }
}

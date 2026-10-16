<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /** Merchants see only their own payments; admins need payments.view (granted via Gate::before). */
    public function view(User $user, Payment $payment): bool
    {
        return $user->merchant_id !== null && $user->merchant_id === $payment->merchant_id;
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function manage(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->merchant_id !== null && $user->merchant_id === $endpoint->merchant_id;
    }
}

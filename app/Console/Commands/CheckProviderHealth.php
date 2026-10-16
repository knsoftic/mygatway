<?php

namespace App\Console\Commands;

use App\Integrations\GatewayManager;
use App\Models\PaymentProvider;
use Illuminate\Console\Command;

class CheckProviderHealth extends Command
{
    protected $signature = 'gateway:check-providers';

    protected $description = 'Check provider API reachability and credentials, and store the result';

    public function handle(GatewayManager $gateways): int
    {
        foreach (PaymentProvider::query()->get() as $provider) {
            if (! $gateways->has($provider->code)) {
                continue;
            }
            $result = $gateways->live($provider->code)->healthCheck();
            $provider->forceFill([
                'health_status' => $result->status,
                'health_message' => mb_substr($result->message, 0, 255),
                'health_checked_at' => now(),
            ])->save();
            $this->line(sprintf('%-12s %-12s %s', $provider->code, $result->status, $result->message));
        }

        return self::SUCCESS;
    }
}

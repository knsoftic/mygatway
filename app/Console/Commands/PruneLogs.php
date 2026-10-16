<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Models\IdempotencyKey;
use App\Models\LoginHistory;
use App\Models\ProviderWebhookLog;
use Illuminate\Console\Command;

/**
 * Removes expired technical logs per config('gateway.retention').
 * Financial records (payments, payouts, ledger, audit logs) are never pruned.
 */
class PruneLogs extends Command
{
    protected $signature = 'gateway:prune-logs';

    protected $description = 'Prune technical logs past their retention period';

    public function handle(): int
    {
        $r = config('gateway.retention');

        $counts = [
            'api_request_logs' => ApiRequestLog::query()->where('created_at', '<', now()->subDays($r['api_request_logs']))->delete(),
            'provider_webhook_logs' => ProviderWebhookLog::query()->where('created_at', '<', now()->subDays($r['provider_webhook_logs']))
                ->whereIn('status', [ProviderWebhookLog::PROCESSED, ProviderWebhookLog::IGNORED, ProviderWebhookLog::DUPLICATE, ProviderWebhookLog::REJECTED])->delete(),
            'login_histories' => LoginHistory::query()->where('created_at', '<', now()->subDays($r['login_histories']))->delete(),
            'idempotency_keys' => IdempotencyKey::query()->where('expires_at', '<', now())->delete(),
        ];

        foreach ($counts as $table => $n) {
            $this->line("{$table}: {$n} removed");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class DispatchDueWebhooks extends Command
{
    protected $signature = 'webhooks:dispatch-due {--limit=500}';

    protected $description = 'Queue merchant webhook deliveries whose retry time has arrived';

    public function handle(): int
    {
        $ids = WebhookDelivery::query()
            ->whereIn('status', [WebhookDelivery::PENDING, WebhookDelivery::RETRYING])
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        $ids->each(fn ($id) => DeliverWebhook::dispatch($id));
        $this->info("Queued {$ids->count()} webhook deliveries.");

        return self::SUCCESS;
    }
}

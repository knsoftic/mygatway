<?php

namespace App\Console\Commands;

use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Integrations\GatewayManager;
use App\Jobs\SubmitPayout;
use App\Jobs\SyncPaymentStatus;
use App\Jobs\SyncPayoutStatus;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

/**
 * Safety net for missed provider callbacks: queues authenticated status checks for
 * open live payments and in-flight payouts, and expires stale sandbox payments.
 */
class SyncPendingTransactions extends Command
{
    protected $signature = 'gateway:sync-pending {--limit=500}';

    protected $description = 'Queue provider status checks for open payments and processing payouts';

    public function handle(GatewayManager $gateways, PaymentService $payments): int
    {
        $limit = (int) $this->option('limit');
        $open = [PaymentStatus::Pending->value, PaymentStatus::Processing->value, PaymentStatus::Confirming->value, PaymentStatus::OnHold->value, PaymentStatus::RefundPending->value];

        $livePayments = Payment::query()
            ->where('mode', Mode::Live->value)->whereIn('status', $open)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes(5)))
            ->orderBy('last_synced_at')->limit($limit)->pluck('id');
        $livePayments->each(fn ($id) => SyncPaymentStatus::dispatch($id));

        $payouts = Payout::query()
            ->where('mode', Mode::Live->value)->where('status', PayoutStatus::Processing->value)
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes(5)))
            ->limit($limit)->pluck('id');
        $payouts->each(fn ($id) => SyncPayoutStatus::dispatch($id));

        // Approved payouts whose submission job was lost (e.g. worker restart).
        Payout::query()->where('status', PayoutStatus::Approved->value)->where('approved_at', '<=', now()->subMinutes(10))
            ->pluck('id')->each(fn ($id) => SubmitPayout::dispatch($id));

        // Sandbox payments are never paid by a provider: expire them once their lifetime is over.
        $expired = 0;
        Payment::query()->with('provider')->where('mode', Mode::Test->value)
            ->whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Pending->value])
            ->where('expires_at', '<=', now())->limit($limit)->get()
            ->each(function (Payment $p) use ($gateways, $payments, &$expired) {
                $payments->applyProviderData($p, $gateways->sandbox($p->provider->code)->simulate($p, 'expired'), 'sandbox');
                $expired++;
            });

        $this->info("Queued {$livePayments->count()} payment and {$payouts->count()} payout status checks; expired {$expired} sandbox payments.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\PaymentProvider;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Console\Command;

class RunReconciliation extends Command
{
    protected $signature = 'gateway:reconcile {--ledger : Check ledger integrity} {--provider= : Provider code to compare against} {--days=2 : Provider window in days}';

    protected $description = 'Run ledger integrity and/or provider reconciliation checks';

    public function handle(ReconciliationService $service): int
    {
        $ran = false;

        if ($this->option('ledger') || ! $this->option('provider')) {
            $run = $service->runLedger();
            $this->line("Ledger: checked {$run->checked_count}, mismatches {$run->mismatch_count}.");
            $ran = true;
        }

        if ($code = $this->option('provider')) {
            $provider = PaymentProvider::findByCode($code);
            if (! $provider) {
                $this->error("Unknown provider [{$code}].");

                return self::FAILURE;
            }
            $run = $service->runProvider($provider, now()->subDays((int) $this->option('days')), now());
            $this->line("{$provider->name}: checked {$run->checked_count}, mismatches {$run->mismatch_count}.");
            $ran = true;
        }

        return $ran ? self::SUCCESS : self::INVALID;
    }
}

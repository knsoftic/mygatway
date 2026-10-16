<?php

namespace App\Console\Commands;

use App\Integrations\Cryptomus\CryptomusGateway;
use App\Integrations\Exceptions\ProviderException;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use Illuminate\Console\Command;

/**
 * Pulls Cryptomus payment/payout services (currencies, networks, limits, commission)
 * from the official API. Binance Pay has no equivalent endpoint, so its currencies
 * are managed manually in the admin panel.
 */
class SyncProviderCurrencies extends Command
{
    protected $signature = 'gateway:sync-currencies';

    protected $description = 'Sync supported currencies and limits from Cryptomus';

    public function handle(CryptomusGateway $cryptomus): int
    {
        $provider = PaymentProvider::findByCode(PaymentProvider::CRYPTOMUS);
        if (! $provider || ! $cryptomus->isConfigured()) {
            $this->warn('Cryptomus is not configured (CRYPTOMUS_MERCHANT_ID / CRYPTOMUS_PAYMENT_KEY). Nothing synced.');

            return self::SUCCESS;
        }

        foreach (['payin', 'payout'] as $direction) {
            if ($direction === 'payout' && ! $cryptomus->payoutsConfigured()) {
                $this->warn('Skipping payout services: CRYPTOMUS_PAYOUT_KEY not set.');

                continue;
            }

            try {
                $services = $cryptomus->listServices($direction);
            } catch (ProviderException $e) {
                $this->error("{$direction}: {$e->getMessage()}");

                continue;
            }

            $seen = [];
            foreach ($services as $s) {
                $row = SupportedCurrency::query()->updateOrCreate(
                    ['provider_id' => $provider->id, 'direction' => $direction, 'currency' => $s['currency'], 'network' => $s['network']],
                    [
                        'min_amount' => $s['min'],
                        'max_amount' => $s['max'],
                        'provider_fee_percent' => $s['fee_percent'],
                        'provider_fee_fixed' => $s['fee_fixed'],
                        'is_available' => $s['is_available'],
                        'source' => 'provider_api',
                        'synced_at' => now(),
                    ],
                );
                $seen[] = $row->id;
            }

            // Anything the provider no longer lists is marked unavailable (admin toggles are kept).
            SupportedCurrency::query()->where('provider_id', $provider->id)->where('direction', $direction)
                ->whereNotIn('id', $seen)->update(['is_available' => false]);

            $this->info(count($services)." {$direction} services synced.");
        }

        return self::SUCCESS;
    }
}

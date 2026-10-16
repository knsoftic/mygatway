<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use Illuminate\Database\Seeder;

/**
 * Initial provider configuration.
 *
 * Cryptomus currencies/networks below are a starting set; run
 * `php artisan gateway:sync-currencies` with live credentials to replace them with
 * the exact list (limits and commissions) returned by /v1/payment/services and
 * /v1/payout/services.
 *
 * Binance Pay has no currency-listing endpoint; the docs name USDT, USDC, BNB and BTC
 * as order currencies. Adjust in Admin -> Providers to match your merchant account.
 */
class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $cryptomus = PaymentProvider::query()->updateOrCreate(['code' => PaymentProvider::CRYPTOMUS], [
            'name' => 'Cryptomus',
            'payin_enabled' => true,
            'payout_enabled' => true,
            'refund_enabled' => true,
            'priority' => 10,
        ]);

        $binance = PaymentProvider::query()->updateOrCreate(['code' => PaymentProvider::BINANCE_PAY], [
            'name' => 'Binance Pay',
            'payin_enabled' => true,
            'payout_enabled' => true,
            'refund_enabled' => true,
            'priority' => 20,
        ]);

        $cryptomusPayin = [
            ['USDT', 'TRON'], ['USDT', 'BSC'], ['USDT', 'ETH'], ['USDT', 'POLYGON'],
            ['BTC', 'BTC'], ['ETH', 'ETH'], ['LTC', 'LTC'], ['TRX', 'TRON'], ['BNB', 'BSC'], ['USDC', 'ETH'],
        ];
        $cryptomusPayout = [['USDT', 'TRON'], ['USDT', 'BSC'], ['USDT', 'ETH'], ['BTC', 'BTC'], ['ETH', 'ETH'], ['LTC', 'LTC']];

        foreach ($cryptomusPayin as [$currency, $network]) {
            $this->currency($cryptomus, 'payin', $currency, $network);
        }
        foreach ($cryptomusPayout as [$currency, $network]) {
            $this->currency($cryptomus, 'payout', $currency, $network);
        }

        foreach (['USDT', 'USDC', 'BNB', 'BTC'] as $currency) {
            $this->currency($binance, 'payin', $currency, '');
            $this->currency($binance, 'payout', $currency, '');
        }
    }

    private function currency(PaymentProvider $provider, string $direction, string $currency, string $network): void
    {
        SupportedCurrency::query()->firstOrCreate(
            ['provider_id' => $provider->id, 'direction' => $direction, 'currency' => $currency, 'network' => $network],
            ['is_available' => true, 'is_enabled' => true, 'source' => 'admin'],
        );
    }
}

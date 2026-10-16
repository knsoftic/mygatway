<?php

namespace Database\Seeders;

use App\Models\FeeRule;
use Illuminate\Database\Seeder;

/**
 * Default platform pricing: 1% on Pay In and 1% on Pay Out.
 * Change or extend in Admin -> Fee rules (changes are versioned and audited).
 */
class FeeRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['payin' => 'Standard Pay In', 'payout' => 'Standard Pay Out'] as $direction => $name) {
            FeeRule::query()->firstOrCreate(
                ['direction' => $direction, 'merchant_id' => null, 'provider_id' => null, 'currency' => null, 'name' => $name],
                ['percentage' => '1.0000', 'fixed_amount' => '0', 'effective_from' => now()->subMinute(), 'is_active' => true],
            );
        }
    }
}

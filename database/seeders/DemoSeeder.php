<?php

namespace Database\Seeders;

use App\Enums\MerchantStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Merchants\MerchantOnboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * LOCAL DEVELOPMENT ONLY - demo accounts for trying the platform.
 *
 *   Admin:    admin@example.test     / Local-Admin-2026!
 *   Merchant: merchant@example.test  / Local-Merchant-2026!
 *
 * The admin must enable 2FA on first login before the admin panel opens.
 * Never run this seeder in production (it refuses to).
 */
class DemoSeeder extends Seeder
{
    public function run(MerchantOnboardingService $onboarding): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $admin = User::query()->firstOrCreate(['email' => 'admin@example.test'], [
            'name' => 'Platform Admin',
            'password' => Hash::make('Local-Admin-2026!'),
        ]);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        $admin->roles()->syncWithoutDetaching(Role::query()->where('name', 'super_admin')->pluck('id'));

        $owner = User::query()->where('email', 'merchant@example.test')->first();
        if (! $owner) {
            $owner = User::query()->create([
                'name' => 'Demo Merchant',
                'email' => 'merchant@example.test',
                'password' => Hash::make('Local-Merchant-2026!'),
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();
            $merchant = $onboarding->register($owner, 'Demo Store Ltd', 'https://demo-store.example');
            $merchant->forceFill(['status' => MerchantStatus::Active])->save();
        }
    }
}

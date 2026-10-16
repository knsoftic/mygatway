<?php

namespace Tests\Feature;

use App\Integrations\Cryptomus\CryptomusGateway;
use App\Models\AuditLog;
use App\Models\PaymentProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\Providers\ProviderCredentials;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PaymentProvider $cryptomus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, ProviderSeeder::class]);
        config(['services.cryptomus.merchant_id' => null, 'services.cryptomus.payment_key' => null, 'services.cryptomus.payout_key' => null]);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true, 'status' => 'active', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->admin->roles()->attach(Role::query()->where('name', 'super_admin')->value('id'));

        $this->cryptomus = PaymentProvider::findByCode(PaymentProvider::CRYPTOMUS);
        Http::fake(['api.cryptomus.com/*' => Http::response(['state' => 0, 'result' => []])]);
    }

    private function save(array $credentials, array $extra = [])
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.providers.index'))
            ->put(route('admin.providers.credentials', $this->cryptomus), ['credentials' => $credentials, 'current_password' => 'password'] + $extra);
    }

    public function test_admin_can_save_keys_which_are_encrypted_masked_and_used_by_the_gateway(): void
    {
        $this->save(['merchant_id' => 'merchant-uuid-1', 'payment_key' => 'secret-payment-key-ABCD'])
            ->assertRedirect(route('admin.providers.index'))
            ->assertSessionHas('success');

        // Encrypted at rest: the raw column never contains the secret.
        $raw = DB::table('payment_providers')->where('code', 'cryptomus')->value('credentials');
        $this->assertStringNotContainsString('secret-payment-key-ABCD', $raw);
        $this->assertSame('secret-payment-key-ABCD', $this->cryptomus->refresh()->credentials['payment_key']);

        // The gateway resolved from the container now uses the saved keys and passed its check.
        $this->assertTrue(app(CryptomusGateway::class)->isConfigured());
        $this->assertSame('healthy', $this->cryptomus->health_status);
        Http::assertSent(fn ($r) => $r->header('merchant')[0] === 'merchant-uuid-1'
            && $r->header('sign')[0] === md5(base64_encode('').'secret-payment-key-ABCD'));

        // The page shows only the last 4 characters.
        $this->actingAs($this->admin)->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('••••ABCD')
            ->assertDontSee('secret-payment-key-ABCD');

        // Audit log records which fields changed, never the values.
        $log = AuditLog::query()->where('action', 'provider.credentials_updated')->sole();
        $this->assertSame(['merchant_id', 'payment_key'], $log->context['fields']);
        $this->assertStringNotContainsString('secret-payment-key', json_encode($log->getAttributes()));
    }

    public function test_blank_fields_keep_the_current_value_and_remove_falls_back_to_env(): void
    {
        $this->save(['merchant_id' => 'merchant-uuid-1', 'payment_key' => 'first-key']);
        $this->save(['merchant_id' => '', 'payment_key' => '']);
        $this->assertSame('first-key', $this->cryptomus->refresh()->credentials['payment_key']);

        config(['services.cryptomus.payment_key' => 'env-key']);
        $this->save([], ['remove' => ['payment_key']]);

        $this->assertArrayNotHasKey('payment_key', $this->cryptomus->refresh()->credentials);
        $this->assertSame('env-key', app(ProviderCredentials::class)->config('cryptomus')['payment_key']);
        $this->assertSame('merchant-uuid-1', app(ProviderCredentials::class)->config('cryptomus')['merchant_id']);
    }

    public function test_wrong_password_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin)->from(route('admin.providers.index'))
            ->put(route('admin.providers.credentials', $this->cryptomus), ['credentials' => ['payment_key' => 'x'], 'current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('credentials_cryptomus', 'current_password');

        $this->assertNull($this->cryptomus->refresh()->credentials);
    }

    public function test_unknown_fields_and_keys_with_spaces_are_rejected(): void
    {
        $this->save(['api_key' => 'not-a-cryptomus-field'])->assertSessionHasErrorsIn('credentials_cryptomus', 'credentials');
        $this->save(['payment_key' => 'has space'])->assertSessionHasErrorsIn('credentials_cryptomus', 'credentials.payment_key');
        $this->assertNull($this->cryptomus->refresh()->credentials);
    }

    public function test_admin_without_provider_permission_cannot_save(): void
    {
        $support = User::factory()->create();
        $support->forceFill(['is_admin' => true, 'status' => 'active', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($support)
            ->put(route('admin.providers.credentials', $this->cryptomus), ['credentials' => ['payment_key' => 'x'], 'current_password' => 'password'])
            ->assertForbidden();
    }

    public function test_credentials_are_never_serialised(): void
    {
        $this->save(['payment_key' => 'secret-payment-key-ABCD']);

        $this->assertArrayNotHasKey('credentials', $this->cryptomus->refresh()->toArray());
    }
}

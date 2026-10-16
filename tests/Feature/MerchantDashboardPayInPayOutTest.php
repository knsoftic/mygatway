<?php

namespace Tests\Feature;

use App\Enums\MerchantStatus;
use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Merchants\MerchantOnboardingService;
use Database\Seeders\FeeRuleSeeder;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Merchant dashboard Pay In ("Receive payment") and Pay Out ("Send payout"):
 * money is credited to and debited from the acting merchant's own balance only.
 */
class MerchantDashboardPayInPayOutTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        $this->seed([RolesAndPermissionsSeeder::class, ProviderSeeder::class, FeeRuleSeeder::class]);

        $this->ownerA = $this->merchantOwner('Shop A', 'a@shop.example');
        $this->ownerB = $this->merchantOwner('Shop B', 'b@shop.example');
    }

    private function merchantOwner(string $business, string $email): User
    {
        $owner = User::factory()->create(['email' => $email]);
        $merchant = app(MerchantOnboardingService::class)->register($owner, $business, null);
        $merchant->forceFill(['status' => MerchantStatus::Active])->save();

        return $owner->refresh();
    }

    private function balance(User $owner, string $currency = 'USDT'): string
    {
        return app(LedgerService::class)->merchantBalances($owner->merchant_id, Mode::Test)[$currency]['available'] ?? '0';
    }

    private function receivePayment(User $owner, string $amount): Payment
    {
        $this->actingAs($owner)->post(route('merchant.payments.store'), [
            'amount' => $amount, 'currency' => 'USDT', 'provider' => 'cryptomus', 'lifetime' => 3600, 'environment' => 'sandbox', 'description' => 'Order 1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payment = Payment::query()->where('merchant_id', $owner->merchant_id)->latest('id')->firstOrFail();

        // Customer pays on the sandbox checkout.
        $this->post(URL::temporarySignedRoute('sandbox.simulate', now()->addHour(), ['payment' => $payment->public_id]), ['outcome' => 'paid'])
            ->assertRedirect();

        return $payment->refresh();
    }

    public function test_sidebar_has_pay_in_and_pay_out_entries(): void
    {
        $this->actingAs($this->ownerA)->get(route('merchant.payments.index'))->assertOk()
            ->assertSee('Receive payment')->assertSee('Send payout')
            ->assertSee(route('merchant.payments.create'), false)
            ->assertSee(route('merchant.payouts.create'), false);

        $this->actingAs($this->ownerA)->get(route('merchant.payments.create'))->assertOk()->assertSee('Create payment link');
    }

    public function test_pay_in_is_credited_to_the_merchant_that_created_it(): void
    {
        $payment = $this->receivePayment($this->ownerA, '100');

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame($this->ownerA->merchant_id, $payment->merchant_id);
        $this->assertNull($payment->api_key_id);
        // 1% default Pay In fee: 100 - 1 = 99 to merchant A, nothing to merchant B.
        $this->assertSame(0, bccomp($this->balance($this->ownerA), '99', 8));
        $this->assertSame(0, bccomp($this->balance($this->ownerB), '0', 8));

        // Merchant B cannot open merchant A's payment.
        $this->actingAs($this->ownerB)->get(route('merchant.payments.show', $payment))->assertForbidden();
    }

    public function test_payment_page_shows_the_checkout_link_while_open(): void
    {
        $this->actingAs($this->ownerA)->post(route('merchant.payments.store'), ['amount' => '10', 'currency' => 'USDT', 'lifetime' => 3600, 'environment' => 'sandbox']);
        $payment = Payment::query()->where('merchant_id', $this->ownerA->merchant_id)->sole();

        $this->assertNotNull($payment->checkout_url);
        $this->actingAs($this->ownerA)->get(route('merchant.payments.show', $payment))->assertOk()
            ->assertSee('Checkout link for your customer')->assertSee($payment->checkout_url);
    }

    public function test_pay_out_is_debited_from_the_merchant_that_requested_it(): void
    {
        $this->receivePayment($this->ownerA, '100');
        $this->receivePayment($this->ownerB, '100');

        $this->actingAs($this->ownerA)->post(route('merchant.payouts.store'), [
            'provider' => 'cryptomus', 'amount' => '50', 'currency' => 'USDT', 'network' => 'TRON',
            'destination_type' => 'crypto_address', 'destination' => 'TQ5nGoodAddressxxxxxxxxxxxxx', 'confirm_destination' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payout = Payout::query()->where('merchant_id', $this->ownerA->merchant_id)->sole();
        $this->assertSame('completed', $payout->refresh()->status->value);

        // A: 99 - total debit (50 + fee + network fee); B untouched at 99.
        $this->assertSame(0, bccomp($this->balance($this->ownerA), bcsub('99', (string) $payout->total_debit, 8), 8));
        $this->assertSame(1, bccomp('99', $this->balance($this->ownerA), 8));
        $this->assertSame(0, bccomp($this->balance($this->ownerB), '99', 8));
        $this->assertSame(0, Payout::query()->where('merchant_id', $this->ownerB->merchant_id)->count());
    }

    public function test_pay_out_above_the_merchants_own_balance_is_refused(): void
    {
        $this->receivePayment($this->ownerB, '1000'); // B is rich, A has nothing.

        $this->actingAs($this->ownerA)->post(route('merchant.payouts.store'), [
            'provider' => 'cryptomus', 'amount' => '50', 'currency' => 'USDT', 'network' => 'TRON',
            'destination_type' => 'crypto_address', 'destination' => 'TQ5nGoodAddressxxxxxxxxxxxxx', 'confirm_destination' => '1',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Payout::query()->count());
        $this->assertSame(0, bccomp($this->balance($this->ownerB), '990', 8));
    }

    public function test_form_offers_sandbox_and_production_and_locks_production_until_verified(): void
    {
        $this->actingAs($this->ownerA)->get(route('merchant.payments.create'))->assertOk()
            ->assertSee('Sandbox (testing)')->assertSee('Production (live)')
            ->assertSee('Available after your', false);
    }

    public function test_production_is_refused_until_live_is_enabled(): void
    {
        $this->actingAs($this->ownerA)->from(route('merchant.payments.create'))->post(route('merchant.payments.store'), [
            'amount' => '10', 'currency' => 'USDT', 'lifetime' => 3600, 'environment' => 'production',
        ])->assertRedirect(route('merchant.payments.create'))->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_production_creates_a_live_payment_with_the_real_provider(): void
    {
        Merchant::query()->whereKey($this->ownerA->merchant_id)->update([
            'verification_status' => 'approved', 'live_enabled' => true, 'live_enabled_at' => now(),
        ]);
        config(['services.cryptomus.merchant_id' => 'm-uuid', 'services.cryptomus.payment_key' => 'pk']);
        Http::fake(['api.cryptomus.com/v1/payment' => Http::response(['state' => 0, 'result' => [
            'uuid' => 'inv-live-1', 'url' => 'https://pay.cryptomus.com/pay/inv-live-1', 'payment_status' => 'check', 'is_final' => false,
        ]])]);

        $this->actingAs($this->ownerA)->get(route('merchant.payments.create'))->assertDontSee('Available after your', false);
        $this->actingAs($this->ownerA)->post(route('merchant.payments.store'), [
            'amount' => '10', 'currency' => 'USDT', 'provider' => 'cryptomus', 'lifetime' => 3600, 'environment' => 'production',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('dashboard_mode', 'live');

        $payment = Payment::query()->sole();
        $this->assertSame(Mode::Live, $payment->mode);
        $this->assertSame('https://pay.cryptomus.com/pay/inv-live-1', $payment->checkout_url);
        Http::assertSentCount(1);
    }

    public function test_environment_is_required(): void
    {
        $this->actingAs($this->ownerA)->post(route('merchant.payments.store'), ['amount' => '10', 'currency' => 'USDT', 'lifetime' => 3600])
            ->assertSessionHasErrors('environment');
    }

    public function test_invalid_amount_is_rejected(): void
    {
        $this->actingAs($this->ownerA)->post(route('merchant.payments.store'), ['amount' => '0', 'currency' => 'USDT', 'lifetime' => 3600, 'environment' => 'sandbox'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::query()->count());
    }
}

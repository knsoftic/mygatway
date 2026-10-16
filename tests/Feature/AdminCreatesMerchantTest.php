<?php

namespace Tests\Feature;

use App\Enums\MerchantStatus;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminCreatesMerchantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true, 'status' => 'active', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->admin->roles()->attach(Role::query()->where('name', 'super_admin')->value('id'));
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Acme Shop',
            'business_website' => 'https://acme.example',
            'owner_name' => 'Sara Owner',
            'owner_email' => 'owner@acme.example',
            'contact_phone' => '+92 300 0000000',
            'status' => 'active',
        ], $overrides);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Password-123!', 'password_confirmation' => 'Password-123!',
            'business_name' => 'X', 'terms' => '1'])->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/register', false);
        $this->get('/login')->assertOk()->assertSee('Request a merchant account');
    }

    public function test_without_mail_setup_the_admin_gets_a_one_time_setup_link(): void
    {
        config(['mail.default' => 'log']);
        $response = $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input());

        $merchant = Merchant::query()->where('name', 'Acme Shop')->sole();
        $owner = User::query()->where('email', 'owner@acme.example')->sole();
        $response->assertRedirect(route('admin.merchants.show', $merchant))->assertSessionHas('merchant_setup_link');

        $this->assertSame($merchant->id, $owner->merchant_id);
        $this->assertSame($owner->id, $merchant->owner_user_id);
        $this->assertSame(MerchantStatus::Active, $merchant->status);
        $this->assertNull($merchant->status_reason);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertFalse($owner->is_admin);
        $this->assertSame('+92 300 0000000', $merchant->profile->contact_phone);
        $this->assertTrue(AuditLog::query()->where('action', 'merchant.created_by_admin')->exists());

        // The link lets the owner choose a password and then log in.
        $link = session('merchant_setup_link');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($link, PHP_URL_PATH));
        auth()->logout();
        $this->post('/reset-password', ['token' => $token, 'email' => $query['email'], 'password' => 'Owner-Chosen-Pass-2026!', 'password_confirmation' => 'Owner-Chosen-Pass-2026!'])
            ->assertSessionHasNoErrors();
        $this->post('/login', ['email' => 'owner@acme.example', 'password' => 'Owner-Chosen-Pass-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($owner->refresh());
    }

    public function test_blank_password_emails_the_setup_link_when_mail_is_configured(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input())
            ->assertSessionHas('success')
            ->assertSessionMissing('merchant_setup_link');

        Notification::assertSentTo(User::query()->where('email', 'owner@acme.example')->sole(), ResetPassword::class);
    }

    public function test_admin_can_set_the_password_and_no_email_is_sent(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input([
            'password' => 'Admin-Set-Pass-2026!', 'password_confirmation' => 'Admin-Set-Pass-2026!',
        ]))->assertSessionHas('success')->assertSessionMissing('merchant_setup_link');

        Notification::assertNothingSent();
        auth()->logout();
        $this->post('/login', ['email' => 'owner@acme.example', 'password' => 'Admin-Set-Pass-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs(User::query()->where('email', 'owner@acme.example')->sole());
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input(['password' => 'Admin-Set-Pass-2026!', 'password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_pending_status_and_validation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input(['status' => 'pending']));
        $this->assertSame(MerchantStatus::Pending, Merchant::query()->sole()->status);

        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input(['business_name' => 'Other']))
            ->assertSessionHasErrors('owner_email');
        $this->actingAs($this->admin)->post(route('admin.merchants.store'), $this->input(['owner_email' => 'new@acme.example', 'status' => 'suspended']))
            ->assertSessionHasErrors('status');
        $this->assertSame(1, Merchant::query()->count());
    }

    public function test_create_page_renders_and_is_not_read_as_a_merchant_id(): void
    {
        $this->actingAs($this->admin)->get(route('admin.merchants.create'))->assertOk()->assertSee('Owner email');
        $this->actingAs($this->admin)->get(route('admin.merchants.index'))->assertOk()->assertSee('New merchant');
    }

    public function test_admin_without_manage_permission_cannot_create(): void
    {
        $viewer = User::factory()->create();
        $viewer->forceFill(['is_admin' => true, 'status' => 'active', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($viewer)->get(route('admin.merchants.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.merchants.store'), $this->input())->assertForbidden();
        $this->assertSame(0, Merchant::query()->count());
    }
}

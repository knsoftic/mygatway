<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports, system settings and admin user management.
 */
class SystemController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function reports(): View
    {
        return view('admin.reports');
    }

    public function export(Request $request): StreamedResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:payments,payouts,revenue'],
            'mode' => ['required', 'in:live,test'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $this->audit->log('report.exported', context: $v);
        $range = [$v['from'].' 00:00:00', $v['to'].' 23:59:59'];
        $name = "{$v['type']}-{$v['mode']}-{$v['from']}-{$v['to']}.csv";

        return match ($v['type']) {
            'payments' => CsvExport::stream($name,
                ['id', 'merchant', 'reference', 'provider', 'provider_payment_id', 'status', 'amount', 'currency', 'received_amount', 'service_fee', 'provider_fee', 'net_amount', 'created_at', 'completed_at'],
                Payment::query()->with(['merchant', 'provider'])->where('mode', $v['mode'])->whereBetween('created_at', $range),
                fn (Payment $p) => [$p->public_id, $p->merchant->name, $p->merchant_reference, $p->provider->code, $p->provider_payment_id, $p->status,
                    $p->amount, $p->currency, $p->received_amount, $p->service_fee, $p->provider_fee, $p->merchant_net_amount,
                    $p->created_at?->toIso8601String(), $p->completed_at?->toIso8601String()]),
            'payouts' => CsvExport::stream($name,
                ['id', 'merchant', 'reference', 'provider', 'status', 'amount', 'currency', 'destination_type', 'destination', 'service_fee', 'network_fee', 'provider_fee', 'total_debit', 'approved_by', 'created_at', 'completed_at'],
                Payout::query()->with(['merchant', 'provider', 'approver'])->where('mode', $v['mode'])->whereBetween('created_at', $range),
                fn (Payout $p) => [$p->public_id, $p->merchant->name, $p->merchant_reference, $p->provider->code, $p->status, $p->amount, $p->currency,
                    $p->destination_type, $p->destination, $p->service_fee, $p->network_fee, $p->provider_fee, $p->total_debit,
                    $p->approver?->email, $p->created_at?->toIso8601String(), $p->completed_at?->toIso8601String()]),
            'revenue' => CsvExport::stream($name,
                ['posted_at', 'transaction', 'account', 'currency', 'direction', 'amount'],
                LedgerEntry::query()->with(['account', 'transaction'])->whereBetween('created_at', $range)
                    ->whereHas('account', fn ($q) => $q->where('mode', $v['mode'])->whereIn('type', [
                        LedgerAccountType::PlatformFeeRevenue->value, LedgerAccountType::PlatformNetworkFee->value, LedgerAccountType::ProviderFeeExpense->value,
                    ])),
                fn (LedgerEntry $e) => [$e->created_at?->toIso8601String(), $e->transaction->public_id, $e->account->type->value, $e->currency, $e->direction, $e->amount]),
        };
    }

    public function editSettings(): View
    {
        return view('admin.settings', ['settings' => collect(SystemSetting::DEFAULTS)->map(fn ($d, $k) => SystemSetting::get($k, $d))]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $v = $request->validate(['platform_support_email' => ['required', 'email:rfc', 'max:255']]);

        $new = [
            'platform.support_email' => $v['platform_support_email'],
            'payouts.manual_approval' => $request->boolean('payouts_manual_approval'),
            'payouts.pass_network_fee_to_merchant' => $request->boolean('payouts_pass_network_fee_to_merchant'),
            'merchants.auto_activate_on_verification' => $request->boolean('merchants_auto_activate_on_verification'),
            'maintenance.block_new_payments' => $request->boolean('maintenance_block_new_payments'),
            'maintenance.block_new_payouts' => $request->boolean('maintenance_block_new_payouts'),
        ];

        $old = collect($new)->map(fn ($_, $k) => SystemSetting::get($k))->all();
        foreach ($new as $key => $value) {
            SystemSetting::put($key, $value, $request->user()->id);
        }
        $this->audit->log('settings.updated', old: $old, new: $new);

        return back()->with('success', 'Settings saved.');
    }

    public function users(): View
    {
        return view('admin.users', [
            'users' => User::query()->with('roles')->where('is_admin', true)->orderBy('name')->get(),
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $user = User::query()->where('email', $v['email'])->first();
        if (! $user) {
            // Random password; the new admin sets their own through the reset link.
            $user = User::query()->create(['name' => $v['name'], 'email' => $v['email'], 'password' => Hash::make(Str::password(32))]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Password::sendResetLink(['email' => $user->email]);
        }
        $user->forceFill(['is_admin' => true, 'status' => 'active'])->save();
        $user->roles()->sync($v['roles']);
        $this->audit->log('admin_user.granted', $user, new: ['roles' => Role::query()->whereIn('id', $v['roles'])->pluck('name')->all()]);

        return back()->with('success', "{$user->email} now has admin access. A password setup link was emailed if the account is new.");
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->is_admin, 404);
        $v = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        if ($user->is($request->user()) && ($v['status'] === 'disabled' || ! in_array(Role::query()->where('name', 'super_admin')->value('id'), $v['roles'] ?? []))) {
            return back()->with('error', 'You cannot disable yourself or remove your own super admin role.');
        }

        $old = ['roles' => $user->roles->pluck('name')->all(), 'status' => $user->status];
        $user->roles()->sync($v['roles'] ?? []);
        $user->forceFill(['status' => $v['status']])->save();
        $this->audit->log('admin_user.updated', $user, $old, ['roles' => $user->roles()->pluck('name')->all(), 'status' => $user->status]);

        return back()->with('success', 'Admin user updated.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MerchantStatus;
use App\Enums\Mode;
use App\Http\Controllers\Controller;
use App\Models\FeeRule;
use App\Models\Merchant;
use App\Models\MerchantLimit;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Merchants\MerchantOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MerchantStatus::class)],
            'verification' => ['nullable', 'in:unverified,submitted,approved,rejected'],
        ]);

        return view('admin.merchants.index', [
            'filters' => $f,
            'merchants' => Merchant::query()->with('owner')
                ->withCount(['payments as live_payments_count' => fn ($q) => $q->where('mode', 'live')])
                ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                    ->orWhere('business_email', 'like', "%{$s}%")->orWhere('public_id', $s)))
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($f['verification'] ?? null, fn ($q, $s) => $q->where('verification_status', $s))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Merchant $merchant, LedgerService $ledger): View
    {
        $merchant->load(['owner', 'profile', 'users', 'limits', 'verifications' => fn ($q) => $q->latest('id'),
            'apiKeys' => fn ($q) => $q->latest('id')]);

        return view('admin.merchants.show', [
            'merchant' => $merchant,
            'balances' => ['live' => $ledger->merchantBalances($merchant->id, Mode::Live), 'test' => $ledger->merchantBalances($merchant->id, Mode::Test)],
            'payments' => $merchant->payments()->with('provider')->where('mode', 'live')->latest('id')->limit(10)->get(),
            'payouts' => $merchant->payouts()->with('provider')->where('mode', 'live')->latest('id')->limit(10)->get(),
            'feeRules' => FeeRule::query()->with('provider')->where('merchant_id', $merchant->id)->latest('id')->get(),
        ]);
    }

    public function updateStatus(Request $request, Merchant $merchant, MerchantOnboardingService $onboarding): RedirectResponse
    {
        $v = $request->validate([
            'status' => ['required', Rule::enum(MerchantStatus::class)],
            'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => in_array($request->input('status'), ['restricted', 'suspended'], true))],
        ]);
        $onboarding->changeStatus($merchant, MerchantStatus::from($v['status']), $v['reason'] ?? null, $request->user());

        return back()->with('success', 'Merchant status updated.');
    }

    public function updateLive(Request $request, Merchant $merchant, MerchantOnboardingService $onboarding): RedirectResponse
    {
        $onboarding->setLiveEnabled($merchant, $request->boolean('live_enabled'), $request->user());

        return back()->with('success', $request->boolean('live_enabled') ? 'Live mode enabled.' : 'Live mode disabled.');
    }

    public function updateLimit(Request $request, Merchant $merchant, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate([
            'direction' => ['required', 'in:payin,payout'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/'],
            'max_per_transaction' => ['nullable', 'string', 'regex:/^\d{1,18}(\.\d{1,8})?$/'],
            'max_daily_total' => ['nullable', 'string', 'regex:/^\d{1,18}(\.\d{1,8})?$/'],
        ]);

        $limit = MerchantLimit::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'direction' => $v['direction'], 'currency' => strtoupper($v['currency'])],
            ['max_per_transaction' => $v['max_per_transaction'] ?: null, 'max_daily_total' => $v['max_daily_total'] ?: null],
        );
        $audit->log('merchant.limit_set', $limit, new: $limit->only(['direction', 'currency', 'max_per_transaction', 'max_daily_total']), merchantId: $merchant->id);

        return back()->with('success', 'Limit saved.');
    }

    public function destroyLimit(Merchant $merchant, MerchantLimit $limit, AuditLogger $audit): RedirectResponse
    {
        abort_unless($limit->merchant_id === $merchant->id, 404);
        $audit->log('merchant.limit_removed', $limit, old: $limit->only(['direction', 'currency', 'max_per_transaction', 'max_daily_total']), merchantId: $merchant->id);
        $limit->delete();

        return back()->with('success', 'Limit removed.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\GatewayManager;
use App\Models\Payout;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\LedgerService;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([...PayoutStatus::values(), 'awaiting'])],
            'mode' => ['nullable', 'in:live,test'],
            'review' => ['nullable', 'boolean'],
        ]);
        $f['mode'] ??= 'live';
        $f['status'] ??= 'awaiting';

        return view('admin.payouts.index', [
            'filters' => $f,
            'payouts' => Payout::query()->with(['merchant', 'provider'])
                ->where('mode', $f['mode'])
                ->when($f['status'] === 'awaiting', fn ($q) => $q->whereIn('status', [PayoutStatus::Requested->value, PayoutStatus::UnderReview->value]))
                ->when($f['status'] !== 'awaiting', fn ($q) => $q->where('status', $f['status']))
                ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('public_id', $s)->orWhere('merchant_reference', $s)->orWhere('destination', $s)->orWhere('tx_hash', $s)))
                ->when($f['review'] ?? false, fn ($q) => $q->where('needs_review', true))
                ->oldest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Payout $payout, LedgerService $ledger, GatewayManager $gateways): View
    {
        $payout->load(['merchant', 'provider', 'approvals.user', 'fees', 'ledgerTransactions.entries.account']);

        return view('admin.payouts.show', [
            'payout' => $payout,
            'merchantBalance' => $ledger->merchantBalances($payout->merchant_id, $payout->mode)[$payout->currency] ?? null,
            'previousPayouts' => Payout::query()->where('merchant_id', $payout->merchant_id)->where('destination', $payout->destination)
                ->where('id', '!=', $payout->id)->latest('id')->limit(5)->get(),
            'providerConfigured' => $payout->mode->value === 'test' || $gateways->live($payout->provider->code)->isConfigured(),
        ]);
    }

    public function approve(Request $request, Payout $payout): RedirectResponse
    {
        $v = $request->validate(['note' => ['nullable', 'string', 'max:1000'], 'confirm' => ['accepted']]);
        $this->payouts->approve($payout, $request->user(), $v['note'] ?? null);

        return back()->with('success', 'Payout approved and queued for submission to the provider.');
    }

    public function reject(Request $request, Payout $payout): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->payouts->reject($payout, $request->user(), $v['reason']);

        return back()->with('success', 'Payout rejected; reserved funds returned to the merchant.');
    }

    public function reverify(Payout $payout, AuditLogger $audit): RedirectResponse
    {
        if ($payout->status !== PayoutStatus::Processing || $payout->mode->value !== 'live') {
            return back()->with('warning', 'Only live payouts that are processing can be re-verified.');
        }

        try {
            $before = $payout->status;
            $payout = $this->payouts->syncWithProvider($payout);
        } catch (ProviderException $e) {
            return back()->with('error', 'Provider query failed: '.$e->safeMessage);
        }
        $audit->log('payout.reverified', $payout, ['status' => $before->value], ['status' => $payout->status->value]);

        return back()->with('success', "Provider reports {$payout->provider_status}; status is now {$payout->status->label()}.");
    }

    public function clearReview(Request $request, Payout $payout, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $payout->forceFill(['needs_review' => false])->save();
        $audit->log('payout.review_cleared', $payout, ['review_reason' => $payout->review_reason], ['needs_review' => false], ['note' => $v['note']]);

        return back()->with('success', 'Review flag cleared.');
    }
}

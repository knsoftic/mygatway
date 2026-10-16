<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Exceptions\ProviderException;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Payout;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentService;
use App\Services\Payouts\PayoutService;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index(): View
    {
        return view('admin.reconciliation.index', [
            'runs' => ReconciliationRun::query()->with(['provider', 'triggeredBy'])->latest('id')->paginate(20),
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
            'openItems' => ReconciliationItem::query()->where('status', 'open')->count(),
        ]);
    }

    public function run(Request $request, ReconciliationService $service): RedirectResponse
    {
        $v = $request->validate(['type' => ['required', 'in:ledger,provider'], 'provider_id' => ['nullable', 'exists:payment_providers,id'], 'days' => ['nullable', 'integer', 'between:1,90']]);

        $run = $v['type'] === 'ledger'
            ? $service->runLedger($request->user())
            : $service->runProvider(PaymentProvider::query()->findOrFail($v['provider_id']), now()->subDays((int) ($v['days'] ?? 2)), now(), $request->user());

        return redirect()->route('admin.reconciliation.show', $run)->with('success', "Checked {$run->checked_count} records; {$run->mismatch_count} issue(s) found.");
    }

    public function show(ReconciliationRun $run): View
    {
        return view('admin.reconciliation.show', ['run' => $run->load('provider'), 'items' => $run->items()->with('subject')->paginate(50)]);
    }

    /** Resolve an item, optionally re-verifying its subject with the provider first. */
    public function resolve(Request $request, ReconciliationItem $item, PaymentService $payments, PayoutService $payouts, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:1000'], 'reverify' => ['nullable', 'boolean']]);

        if ($request->boolean('reverify')) {
            try {
                match (true) {
                    $item->subject instanceof Payment => $payments->syncWithProvider($item->subject, 'reconciliation'),
                    $item->subject instanceof Payout => $payouts->syncWithProvider($item->subject),
                    default => null,
                };
            } catch (ProviderException $e) {
                return back()->with('error', 'Re-verification failed: '.$e->safeMessage);
            }
        }

        $item->forceFill(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()])->save();
        $audit->log('reconciliation.item_resolved', $item, context: ['note' => $v['note'], 'reverified' => $request->boolean('reverify')]);

        return back()->with('success', 'Item resolved.');
    }
}

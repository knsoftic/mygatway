<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Exceptions\ProviderException;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\ProviderWebhookLog;
use App\Models\WebhookEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentService;
use App\Services\Refunds\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pay In monitoring. Admins can never set a payment to "completed" by hand: the only
 * way to change a status is re-verifying with the provider (verified data) or a refund.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'mode' => ['nullable', 'in:live,test'],
            'provider' => ['nullable', 'string', 'max:32'],
            'review' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $f['mode'] ??= 'live';

        return view('admin.payments.index', [
            'filters' => $f,
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
            'payments' => Payment::query()->with(['merchant', 'provider'])
                ->where('mode', $f['mode'])
                ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('public_id', $s)->orWhere('merchant_reference', $s)
                    ->orWhere('provider_payment_id', $s)->orWhere('provider_order_id', $s)->orWhere('tx_hash', $s)))
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($f['provider'] ?? null, fn ($q, $p) => $q->whereHas('provider', fn ($w) => $w->where('code', $p)))
                ->when($f['review'] ?? false, fn ($q) => $q->where('needs_review', true))
                ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['merchant', 'provider', 'fees.rule', 'refunds', 'apiKey', 'ledgerTransactions.entries.account']);

        return view('admin.payments.show', [
            'payment' => $payment,
            'providerLogs' => ProviderWebhookLog::query()->where('provider_id', $payment->provider_id)
                ->where(fn ($q) => $q->where('external_id', $payment->provider_payment_id ?? '-')->orWhere('raw_body', 'like', '%'.$payment->provider_order_id.'%'))
                ->latest('id')->limit(20)->get(),
            'events' => WebhookEvent::query()->where('resource_type', $payment->getMorphClass())->where('resource_id', $payment->id)
                ->with('deliveries.endpoint')->latest('id')->get(),
        ]);
    }

    /** Authenticated provider status query; applies verified data through the normal path. */
    public function reverify(Request $request, Payment $payment, PaymentService $payments, AuditLogger $audit): RedirectResponse
    {
        if ($payment->mode->value !== 'live') {
            return back()->with('warning', 'Test-mode payments are simulated and have no provider record.');
        }

        try {
            $before = $payment->status;
            $payment = $payments->syncWithProvider($payment, 'reconciliation');
        } catch (ProviderException $e) {
            return back()->with('error', 'Provider query failed: '.$e->safeMessage);
        }

        $audit->log('payment.reverified', $payment, ['status' => $before->value], ['status' => $payment->status->value]);

        return back()->with('success', "Provider reports {$payment->provider_status}; status is now {$payment->status->label()}.");
    }

    public function refund(Request $request, Payment $payment, RefundService $refunds): RedirectResponse
    {
        $v = $request->validate([
            'address' => [Rule::requiredIf($payment->provider->code === PaymentProvider::CRYPTOMUS && $payment->mode->value === 'live'), 'nullable', 'string', 'regex:/^[A-Za-z0-9:_\-.]{20,128}$/'],
            'reason' => ['required', 'string', 'max:255'],
            'confirm' => ['accepted'],
        ]);

        $refund = $refunds->request($payment, $request->user(), $v['address'] ?? null, $v['reason']);

        return back()->with('success', "Refund {$refund->public_id} is {$refund->status->value}.");
    }

    public function clearReview(Request $request, Payment $payment, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $old = ['needs_review' => true, 'review_reason' => $payment->review_reason];
        $payment->forceFill(['needs_review' => false])->save();
        $audit->log('payment.review_cleared', $payment, $old, ['needs_review' => false], ['note' => $v['note']]);

        return back()->with('success', 'Review flag cleared.');
    }
}

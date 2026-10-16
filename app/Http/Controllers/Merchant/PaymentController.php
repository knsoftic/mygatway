<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\WebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $mode = $request->attributes->get('dashboard_mode');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'currency' => ['nullable', 'string', 'max:16'],
            'provider' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $payments = Payment::query()->with('provider')
            ->where('merchant_id', $request->user()->merchant_id)
            ->where('mode', $mode->value)
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('public_id', $s)->orWhere('merchant_reference', 'like', $s.'%')->orWhere('tx_hash', $s)))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['currency'] ?? null, fn ($q, $c) => $q->where('currency', strtoupper($c)))
            ->when($filters['provider'] ?? null, fn ($q, $p) => $q->whereHas('provider', fn ($w) => $w->where('code', $p)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('merchant.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
        ]);
    }

    public function show(Request $request, Payment $payment): View
    {
        $this->authorize('view', $payment);
        $payment->load(['provider', 'fees', 'refunds', 'ledgerTransactions.entries.account']);

        return view('merchant.payments.show', [
            'payment' => $payment,
            'events' => WebhookEvent::query()->where('resource_type', $payment->getMorphClass())->where('resource_id', $payment->id)
                ->with('deliveries.endpoint')->latest('id')->get(),
        ]);
    }
}

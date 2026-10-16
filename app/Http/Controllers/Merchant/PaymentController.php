<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /** "Receive payment": a Pay In created from the dashboard instead of the API. */
    public function create(Request $request): View
    {
        $mode = $request->attributes->get('dashboard_mode');
        $currencies = SupportedCurrency::query()->usable()->where('direction', 'payin')
            ->whereHas('provider', fn ($q) => $q->where('payin_enabled', true))
            ->with('provider')->orderBy('currency')->get();

        $merchant = $request->user()->merchant;

        return view('merchant.payments.create', [
            'mode' => $mode,
            'currencies' => $currencies->pluck('currency')->unique()->values(),
            'providers' => $currencies->pluck('provider')->unique('id')->sortBy('priority')->values(),
            'canSandbox' => $merchant->canTransact(Mode::Test),
            'canProduction' => $merchant->canTransact(Mode::Live),
        ]);
    }

    /**
     * Creates the payment through the same service as POST /api/v1/payments, so fees,
     * limits, provider routing and ledger crediting are identical. The amount is credited
     * to this merchant's balance only when the provider confirms the payment.
     */
    public function store(Request $request, PaymentService $payments): RedirectResponse
    {
        $request->merge([
            'currency' => strtoupper((string) $request->input('currency')),
            'reference' => $request->input('reference') ?: 'dash-'.Str::lower(Str::random(12)),
        ]);
        $v = $request->validate([
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,17})(?:\.\d{1,8})?$/', 'not_regex:/^0(?:\.0+)?$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z0-9]{2,16}$/'],
            'provider' => ['nullable', Rule::in(['cryptomus', 'binance_pay'])],
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{1,64}$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'lifetime' => ['required', 'integer', 'between:'.config('gateway.payments.min_lifetime').','.config('gateway.payments.max_lifetime')],
            // sandbox = test mode (simulator, no real money); production = live mode (real provider).
            'environment' => ['required', 'in:sandbox,production'],
        ], [
            'amount.regex' => 'Enter an amount like 25 or 25.50 (up to 8 decimals).',
            'amount.not_regex' => 'The amount must be greater than zero.',
            'reference.regex' => 'Use only letters, numbers, - and _ (max 64).',
        ]);

        $mode = $v['environment'] === 'production' ? Mode::Live : Mode::Test;
        $payment = $payments->create($request->user()->merchant, null, $mode, $v);

        // Show the dashboard in the payment's mode, so it appears in Payments and its balance.
        $request->session()->put('dashboard_mode', $mode->value);

        return redirect()->route('merchant.payments.show', $payment)
            ->with('success', ($mode === Mode::Live ? 'Production' : 'Sandbox').' payment created. Send the checkout link to your customer; your balance is credited when the payment is confirmed.');
    }

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

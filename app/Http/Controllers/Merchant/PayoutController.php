<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\Mode;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StorePayoutRequest;
use App\Integrations\GatewayManager;
use App\Models\PaymentProvider;
use App\Models\Payout;
use App\Models\SupportedCurrency;
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
        $mode = $request->attributes->get('dashboard_mode');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(PayoutStatus::values())],
            'currency' => ['nullable', 'string', 'max:16'],
        ]);

        return view('merchant.payouts.index', [
            'filters' => $filters,
            'payouts' => Payout::query()->with('provider')
                ->where('merchant_id', $request->user()->merchant_id)->where('mode', $mode->value)
                ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('public_id', $s)->orWhere('merchant_reference', 'like', $s.'%')->orWhere('destination', $s)))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['currency'] ?? null, fn ($q, $c) => $q->where('currency', strtoupper($c)))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request, LedgerService $ledger, GatewayManager $gateways): View
    {
        $mode = $request->attributes->get('dashboard_mode');
        $providers = PaymentProvider::query()->where('payout_enabled', true)->orderBy('priority')->get()
            ->filter(fn ($p) => $gateways->has($p->code) && $gateways->live($p->code)->capabilities()->payOut)->values();

        return view('merchant.payouts.create', [
            'mode' => $mode,
            'balances' => $ledger->merchantBalances($request->user()->merchant_id, $mode),
            'providers' => $providers->map(fn ($p) => [
                'code' => $p->code,
                'name' => $p->name,
                'destinations' => array_map(fn (PayoutDestinationType $t) => ['value' => $t->value, 'label' => $t->label()], $gateways->live($p->code)->capabilities()->payoutDestinationTypes),
                'currencies' => SupportedCurrency::query()->where('provider_id', $p->id)->where('direction', 'payout')->usable()
                    ->orderBy('currency')->get(['currency', 'network', 'min_amount', 'max_amount'])->toArray(),
            ]),
            'canRequest' => $mode === Mode::Test || ($request->user()->merchant->isLiveEnabled() && $request->user()->hasTwoFactorEnabled()),
        ]);
    }

    public function store(StorePayoutRequest $request): RedirectResponse
    {
        $payout = $this->payouts->request(
            $request->user()->merchant,
            null,
            $request->user(),
            $request->attributes->get('dashboard_mode'),
            $request->payoutInput(),
        );

        return redirect()->route('merchant.payouts.show', $payout)->with('success', $payout->status === PayoutStatus::UnderReview
            ? 'Payout requested. The amount is reserved and the payout is awaiting approval.'
            : 'Payout requested.');
    }

    public function show(Request $request, Payout $payout): View
    {
        $this->authorize('view', $payout);

        return view('merchant.payouts.show', ['payout' => $payout->load(['provider', 'approvals.user', 'fees'])]);
    }

    public function cancel(Request $request, Payout $payout): RedirectResponse
    {
        $this->authorize('cancel', $payout);
        $this->payouts->cancel($payout, $request->user());

        return back()->with('success', 'Payout cancelled and the reserved amount returned to your balance.');
    }
}

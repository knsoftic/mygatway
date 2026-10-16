<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FeeDirection;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\LedgerTransactionResource;
use App\Http\Responses\ApiError;
use App\Integrations\GatewayManager;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use App\Models\Transaction;
use App\Services\Fees\FeeCalculator;
use App\Services\Ledger\LedgerService;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Balance, ledger transactions, fees and supported currencies for the authenticated merchant.
 */
class AccountController extends Controller
{
    /** GET /api/v1/balance - per currency; currencies are never combined. */
    public function balance(Request $request, LedgerService $ledger): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $mode = $request->attributes->get('mode');
        $balances = $ledger->merchantBalances($merchant->id, $mode);

        $pending = Payment::query()
            ->where('merchant_id', $merchant->id)->where('mode', $mode->value)
            ->whereIn('status', [PaymentStatus::Processing->value, PaymentStatus::Confirming->value])
            ->selectRaw('currency, SUM(amount) AS total')->groupBy('currency')->pluck('total', 'currency');

        $currencies = array_unique(array_merge(array_keys($balances), $pending->keys()->all()));
        sort($currencies);

        return response()->json([
            'object' => 'balance',
            'mode' => $mode->value,
            'balances' => array_map(fn (string $c) => [
                'currency' => $c,
                'available' => $balances[$c]['available'] ?? Decimal::zero(),
                'reserved' => $balances[$c]['reserved'] ?? Decimal::zero(),
                // Gross amount of payments seen on-chain but not yet confirmed (not yet credited).
                'pending' => Decimal::of((string) ($pending[$c] ?? '0')),
            ], $currencies),
        ]);
    }

    /** GET /api/v1/transactions - ledger journal history (balance changes). */
    public function transactions(ListRequest $request): AnonymousResourceCollection
    {
        $txs = Transaction::query()
            ->with(['entries.account', 'reference'])
            ->where('merchant_id', $request->merchant()->id)
            ->where('mode', $request->mode()->value)
            ->when($request->validated('currency'), fn ($q, $c) => $q->where('currency', strtoupper($c)))
            ->when($request->validated('created_from'), fn ($q, $d) => $q->where('posted_at', '>=', $d))
            ->when($request->validated('created_to'), fn ($q, $d) => $q->where('posted_at', '<=', $d))
            ->orderByDesc('id')
            ->cursorPaginate($request->limit());

        return LedgerTransactionResource::collection($txs);
    }

    /** GET /api/v1/fees - the fee rules currently applied to this merchant. */
    public function fees(Request $request, FeeCalculator $fees): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $rows = [];

        $currencies = SupportedCurrency::query()->usable()->with('provider')->get()
            ->filter(fn ($c) => $c->direction === 'payin' ? $c->provider->payin_enabled : $c->provider->payout_enabled)
            ->unique(fn ($c) => $c->provider_id.'|'.$c->direction.'|'.$c->currency);

        foreach ($currencies as $c) {
            $direction = FeeDirection::from($c->direction);
            $rule = $fees->resolveRule($direction, $merchant->id, $c->provider_id, $c->currency);
            $rows[] = [
                'direction' => $direction->value,
                'provider' => $c->provider->code,
                'currency' => $c->currency,
                'percentage' => $rule ? (string) $rule->percentage : '0.0000',
                'fixed_amount' => $rule ? (string) $rule->fixed_amount : Decimal::zero(),
                'min_fee' => $rule?->min_fee,
                'max_fee' => $rule?->max_fee,
            ];
        }

        return response()->json(['object' => 'list', 'data' => $rows]);
    }

    /** GET /api/v1/fees/quote?direction=payin&provider=cryptomus&currency=USDT&amount=100 */
    public function quote(Request $request, FeeCalculator $fees): JsonResponse
    {
        $v = $request->validate([
            'direction' => ['required', 'in:payin,payout'],
            'provider' => ['required', 'in:cryptomus,binance_pay'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/'],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,17})(?:\.\d{1,8})?$/'],
        ]);

        $provider = PaymentProvider::findByCode($v['provider']);
        if (! $provider) {
            return ApiError::make('provider_not_available', 'Unknown provider.', 422);
        }

        $direction = FeeDirection::from($v['direction']);
        $quote = $fees->quote($direction, $request->attributes->get('merchant')->id, $provider->id, $v['currency'], $v['amount']);

        return response()->json([
            'object' => 'fee_quote',
            'direction' => $direction->value,
            'provider' => $provider->code,
            'currency' => strtoupper($v['currency']),
            'amount' => Decimal::of($v['amount']),
            'service_fee' => $quote->amount,
            'net_amount' => $direction === FeeDirection::PayIn ? Decimal::sub(Decimal::of($v['amount']), $quote->amount) : null,
            'note' => $direction === FeeDirection::PayOut ? 'Payouts may also include a provider network fee, shown when the payout is created.' : null,
        ]);
    }

    /** GET /api/v1/currencies - supported currencies/networks per provider and direction. */
    public function currencies(GatewayManager $gateways): JsonResponse
    {
        $rows = SupportedCurrency::query()->usable()->with('provider')->orderBy('currency')->get()
            ->filter(fn ($c) => $c->direction === 'payin' ? $c->provider->payin_enabled : $c->provider->payout_enabled)
            ->map(fn (SupportedCurrency $c) => [
                'provider' => $c->provider->code,
                'direction' => $c->direction,
                'currency' => $c->currency,
                'network' => $c->network !== '' ? $c->network : null,
                'min_amount' => $c->min_amount,
                'max_amount' => $c->max_amount,
            ])->values();

        $providers = PaymentProvider::query()->where(fn ($q) => $q->where('payin_enabled', true)->orWhere('payout_enabled', true))->get()
            ->filter(fn ($p) => $gateways->has($p->code))
            ->map(fn ($p) => [
                'code' => $p->code,
                'name' => $p->name,
                'pay_in' => $p->payin_enabled,
                'pay_out' => $p->payout_enabled,
                'capabilities' => $gateways->live($p->code)->capabilities()->toArray(),
            ])->values();

        return response()->json(['object' => 'list', 'providers' => $providers, 'data' => $rows]);
    }
}

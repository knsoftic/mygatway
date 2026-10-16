<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Payout;
use App\Services\Ledger\LedgerService;
use App\Services\Reporting\TransactionStats;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, LedgerService $ledger, TransactionStats $stats): View
    {
        $merchant = $request->user()->merchant;
        /** @var Mode $mode */
        $mode = $request->attributes->get('dashboard_mode');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'max:16'],
            'provider' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:settled,open,unsuccessful'],
        ]);
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();
        $providerId = isset($filters['provider']) ? PaymentProvider::findByCode($filters['provider'])?->id : null;

        $scope = function ($query) use ($merchant, $mode, $from, $to, $filters, $providerId) {
            return $query->where('merchant_id', $merchant->id)->where('mode', $mode->value)
                ->whereBetween('created_at', [$from, $to])
                ->when($filters['currency'] ?? null, fn ($q, $c) => $q->where('currency', strtoupper($c)))
                ->when($providerId, fn ($q, $p) => $q->where('provider_id', $p));
        };

        $payments = $scope(Payment::query())
            ->when($filters['status'] ?? null, fn ($q, $g) => $q->whereIn('status', TransactionStats::statusesFor($g)));
        $payouts = $scope(Payout::query());

        $byCurrency = $stats->perCurrency($payments, $payouts);
        $balances = $ledger->merchantBalances($merchant->id, $mode);

        $pendingBalances = Payment::query()->where('merchant_id', $merchant->id)->where('mode', $mode->value)
            ->whereIn('status', [PaymentStatus::Processing->value, PaymentStatus::Confirming->value])
            ->selectRaw('currency, SUM(amount) AS total')->groupBy('currency')->pluck('total', 'currency');

        $chartCurrency = strtoupper($filters['currency'] ?? '') ?: ($byCurrency->sortByDesc(fn ($r) => (float) $r['payin_volume'])->keys()->first()
            ?? array_key_first($balances) ?? 'USDT');

        $base = fn ($q) => $q->where('merchant_id', $merchant->id)->where('mode', $mode->value);

        return view('merchant.dashboard', [
            'merchant' => $merchant,
            'mode' => $mode,
            'filters' => $filters + ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'byCurrency' => $byCurrency,
            'balances' => $balances,
            'pendingBalances' => $pendingBalances->map(fn ($v) => Decimal::of((string) $v)),
            'chartCurrency' => $chartCurrency,
            'charts' => [
                'daily' => $stats->series($base(Payment::query()), $base(Payout::query()), $chartCurrency, 'day', 30),
                'weekly' => $stats->series($base(Payment::query()), $base(Payout::query()), $chartCurrency, 'week', 12),
                'monthly' => $stats->series($base(Payment::query()), $base(Payout::query()), $chartCurrency, 'month', 12),
            ],
            'recentPayments' => $base(Payment::query())->with('provider')->latest('id')->limit(8)->get(),
            'recentPayouts' => $base(Payout::query())->with('provider')->latest('id')->limit(5)->get(),
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
            'hasApiKeys' => $merchant->apiKeys()->whereNull('revoked_at')->exists(),
        ]);
    }

    public function switchMode(Request $request): RedirectResponse
    {
        $mode = Mode::from($request->validate(['mode' => ['required', 'in:test,live']])['mode']);
        $request->session()->put('dashboard_mode', $mode->value);

        $message = $mode === Mode::Live && ! $request->user()->merchant->isLiveEnabled()
            ? 'Live mode is not enabled yet. Complete business verification to accept live payments.'
            : null;

        return back()->with($message ? 'warning' : 'success', $message ?? "Switched to {$mode->label()} mode.");
    }
}

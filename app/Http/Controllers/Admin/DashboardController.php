<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerAccountType;
use App\Enums\MerchantStatus;
use App\Enums\Mode;
use App\Enums\PayoutStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\LedgerAccount;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Payout;
use App\Models\RiskEvent;
use App\Services\Reporting\TransactionStats;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TransactionStats $stats): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'mode' => ['nullable', 'in:live,test'],
        ]);
        $mode = Mode::from($filters['mode'] ?? 'live');
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $payments = Payment::query()->where('mode', $mode->value)->whereBetween('created_at', [$from, $to]);
        $payouts = Payout::query()->where('mode', $mode->value)->whereBetween('created_at', [$from, $to]);

        // Platform P&L per currency straight from ledger balances (all time).
        $revenue = LedgerAccount::query()->where('mode', $mode->value)
            ->whereIn('type', [LedgerAccountType::PlatformFeeRevenue->value, LedgerAccountType::PlatformNetworkFee->value, LedgerAccountType::ProviderFeeExpense->value])
            ->get()->groupBy('currency')->map(function ($accounts) {
                $sum = fn (LedgerAccountType $t) => $accounts->where('type', $t)->reduce(fn ($c, $a) => Decimal::add($c, (string) $a->balance), Decimal::zero());
                $service = $sum(LedgerAccountType::PlatformFeeRevenue);
                $network = $sum(LedgerAccountType::PlatformNetworkFee);
                $providerFees = $sum(LedgerAccountType::ProviderFeeExpense);

                return [
                    'service' => $service,
                    'network' => $network,
                    'provider' => $providerFees,
                    'net' => Decimal::sub(Decimal::add($service, $network), $providerFees),
                ];
            })->sortKeys();

        return view('admin.dashboard', [
            'mode' => $mode,
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'mode' => $mode->value],
            'merchantCounts' => [
                'total' => Merchant::query()->count(),
                'active' => Merchant::query()->where('status', MerchantStatus::Active->value)->count(),
                'pending_approval' => Merchant::query()->where('verification_status', VerificationStatus::Submitted->value)->count(),
                'live' => Merchant::query()->where('live_enabled', true)->count(),
            ],
            'byCurrency' => $stats->perCurrency($payments, $payouts),
            'revenue' => $revenue,
            'payoutsAwaiting' => Payout::query()->where('mode', Mode::Live->value)
                ->whereIn('status', [PayoutStatus::Requested->value, PayoutStatus::UnderReview->value])->count(),
            'needsReview' => Payment::query()->where('needs_review', true)->count() + Payout::query()->where('needs_review', true)->count(),
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
            'risk' => RiskEvent::query()->whereNull('resolved_at')->latest('id')->limit(6)->get(),
        ]);
    }
}

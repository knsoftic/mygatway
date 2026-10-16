<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Ledger\LedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, LedgerService $ledger): View
    {
        $mode = $request->attributes->get('dashboard_mode');
        $merchantId = $request->user()->merchant_id;
        $filters = $request->validate([
            'currency' => ['nullable', 'string', 'max:16'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        return view('merchant.ledger.index', [
            'balances' => $ledger->merchantBalances($merchantId, $mode),
            'filters' => $filters,
            'types' => [
                Transaction::PAYIN_SETTLEMENT, Transaction::PAYOUT_RESERVE, Transaction::PAYOUT_SETTLEMENT,
                Transaction::PAYOUT_RELEASE, Transaction::REFUND_RESERVE, Transaction::REFUND_SETTLEMENT,
                Transaction::REFUND_RELEASE, Transaction::REVERSAL,
            ],
            'transactions' => Transaction::query()
                ->with(['entries.account', 'reference'])
                ->where('merchant_id', $merchantId)->where('mode', $mode->value)
                ->when($filters['currency'] ?? null, fn ($q, $c) => $q->where('currency', strtoupper($c)))
                ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}

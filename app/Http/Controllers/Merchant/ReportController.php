<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('merchant.reports.index');
    }

    public function export(Request $request): StreamedResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:payments,payouts,ledger'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'max:16'],
        ]);
        $mode = $request->attributes->get('dashboard_mode');
        $merchantId = $request->user()->merchant_id;
        $stamp = now()->format('Ymd-His');

        $scope = fn ($q, string $dateCol = 'created_at') => $q->where('merchant_id', $merchantId)->where('mode', $mode->value)
            ->when($v['from'] ?? null, fn ($w, $d) => $w->where($dateCol, '>=', $d))
            ->when($v['to'] ?? null, fn ($w, $d) => $w->where($dateCol, '<=', $d.' 23:59:59'))
            ->when($v['currency'] ?? null, fn ($w, $c) => $w->where('currency', strtoupper($c)));

        return match ($v['type']) {
            'payments' => CsvExport::stream("payments-{$mode->value}-{$stamp}.csv",
                ['id', 'reference', 'status', 'provider', 'amount', 'currency', 'network', 'received_amount', 'service_fee', 'net_amount', 'tx_hash', 'created_at', 'completed_at'],
                $scope(Payment::query()->with('provider'))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s)),
                fn (Payment $p) => [$p->public_id, $p->merchant_reference, $p->status, $p->provider->code, $p->amount, $p->currency, $p->network,
                    $p->received_amount, $p->service_fee, $p->merchant_net_amount, $p->tx_hash, $p->created_at?->toIso8601String(), $p->completed_at?->toIso8601String()]),
            'payouts' => CsvExport::stream("payouts-{$mode->value}-{$stamp}.csv",
                ['id', 'reference', 'status', 'provider', 'amount', 'currency', 'network', 'destination_type', 'destination', 'service_fee', 'network_fee', 'total_debit', 'tx_hash', 'created_at', 'completed_at'],
                $scope(Payout::query()->with('provider'))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s)),
                fn (Payout $p) => [$p->public_id, $p->merchant_reference, $p->status, $p->provider->code, $p->amount, $p->currency, $p->network,
                    $p->destination_type, $p->destination, $p->service_fee, $p->network_fee, $p->total_debit, $p->tx_hash,
                    $p->created_at?->toIso8601String(), $p->completed_at?->toIso8601String()]),
            'ledger' => CsvExport::stream("ledger-{$mode->value}-{$stamp}.csv",
                ['transaction', 'posted_at', 'type', 'currency', 'balance', 'direction', 'amount', 'balance_after', 'description'],
                $scope(Transaction::query()->with('entries.account'), 'posted_at'),
                function (Transaction $t) {
                    // One CSV row per transaction, showing the movement on the available balance.
                    $e = $t->entries->first(fn ($e) => $e->account->merchant_id === $t->merchant_id && $e->account->type->value === 'merchant_available');

                    return [$t->public_id, $t->posted_at?->toIso8601String(), $t->type, $t->currency, 'available',
                        $e?->direction?->value, $e?->amount, $e?->balance_after, $t->description];
                }),
        };
    }

    public function apiLogs(Request $request): View
    {
        $v = $request->validate(['status' => ['nullable', 'in:success,error'], 'key' => ['nullable', 'integer']]);
        $merchant = $request->user()->merchant;

        return view('merchant.api-logs', [
            'logs' => ApiRequestLog::query()->with('apiKey')
                ->where('merchant_id', $merchant->id)
                ->when(($v['status'] ?? null) === 'error', fn ($q) => $q->where('status_code', '>=', 400))
                ->when(($v['status'] ?? null) === 'success', fn ($q) => $q->where('status_code', '<', 400))
                ->when($v['key'] ?? null, fn ($q, $k) => $q->where('api_key_id', $k))
                ->latest('id')->paginate(50)->withQueryString(),
            'keys' => $merchant->apiKeys()->get(['id', 'name', 'key_id']),
            'filters' => $v,
        ]);
    }
}

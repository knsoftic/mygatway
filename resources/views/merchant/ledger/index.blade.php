@extends('layouts.app', ['title' => 'Balance & ledger'])

@section('header')
    <div><h1 class="page-title">Balance &amp; ledger</h1><div class="small text-muted-2">Every balance change is an immutable, balanced journal entry. {{ $dashboardMode->label() }} mode.</div></div>
    @if (Route::has('merchant.reports.export'))<a class="btn btn-outline-secondary" href="{{ route('merchant.reports.export', ['type' => 'ledger'] + request()->query()) }}"><i class="bi bi-download"></i> Export CSV</a>@endif
@endsection

@section('content')
    <div class="row g-3 mb-4">
        @forelse ($balances as $cur => $b)
            <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <div class="fw-bold mb-1">{{ $cur }}</div>
                <div class="d-flex justify-content-between small"><span class="text-muted-2">Available</span><x-money :amount="$b['available']" class="fw-semibold" /></div>
                <div class="d-flex justify-content-between small"><span class="text-muted-2">Reserved</span><x-money :amount="$b['reserved']" /></div>
            </div></div></div>
        @empty
            <div class="col-12"><div class="card"><x-empty-state icon="wallet2" title="No balances yet" /></div></div>
        @endforelse
    </div>

    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input name="currency" value="{{ $filters['currency'] ?? '' }}" class="form-control form-control-sm text-uppercase" style="max-width:9rem" placeholder="Currency" aria-label="Currency">
        <select name="type" class="form-select form-select-sm" style="max-width:14rem" aria-label="Type"><option value="">All types</option>
            @foreach ($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ \App\Models\Transaction::typeLabel($t) }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-primary">Filter</button>
    </form>

    <div class="card">
        @if ($transactions->isEmpty())
            <x-empty-state icon="journal-text" title="No ledger entries" text="Entries appear when payments settle or payouts are requested." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Posted</th><th>Transaction</th><th>Type</th><th>Source</th><th class="text-end">Available</th><th class="text-end">Reserved</th><th class="text-end">Available after</th></tr></thead>
                <tbody>
                @foreach ($transactions as $tx)
                    @php
                        $mine = $tx->entries->filter(fn ($e) => $e->account->merchant_id === $tx->merchant_id && $e->account->type->isMerchantAccount());
                        $delta = fn ($type) => $mine->filter(fn ($e) => $e->account->type->value === $type)->reduce(fn ($c, $e) => \App\Support\Decimal::add($c, $e->direction->value === 'credit' ? (string) $e->amount : \App\Support\Decimal::negate((string) $e->amount)), '0');
                        $avail = $delta('merchant_available'); $res = $delta('merchant_reserved');
                        $after = $mine->first(fn ($e) => $e->account->type->value === 'merchant_available')?->balance_after;
                    @endphp
                    <tr>
                        <td class="small text-nowrap">{{ $tx->posted_at->format('M j, H:i') }}</td>
                        <td class="font-mono small">{{ $tx->public_id }}</td>
                        <td>{{ \App\Models\Transaction::typeLabel($tx->type) }}</td>
                        <td class="font-mono small">
                            @if ($tx->reference instanceof \App\Models\Payment)<a href="{{ route('merchant.payments.show', $tx->reference) }}">{{ $tx->reference->public_id }}</a>
                            @elseif ($tx->reference instanceof \App\Models\Payout)<a href="{{ route('merchant.payouts.show', $tx->reference) }}">{{ $tx->reference->public_id }}</a>
                            @else {{ $tx->reference->public_id ?? '—' }} @endif
                        </td>
                        <td class="text-end amount {{ \App\Support\Decimal::isNegative($avail) ? 'text-danger' : (\App\Support\Decimal::isZero($avail) ? 'text-muted-2' : 'text-success') }}">{{ \App\Support\Decimal::isPositive($avail) ? '+' : '' }}{{ \App\Support\Decimal::format($avail) }} {{ $tx->currency }}</td>
                        <td class="text-end amount text-muted-2">{{ \App\Support\Decimal::isPositive($res) ? '+' : '' }}{{ \App\Support\Decimal::format($res) }}</td>
                        <td class="text-end amount">{{ $after !== null ? \App\Support\Decimal::format((string) $after) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $transactions->links() }}</div>
        @endif
    </div>
@endsection

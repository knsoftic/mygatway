@extends('layouts.app', ['title' => 'Payouts'])

@section('header')
    <div><h1 class="page-title">Payouts</h1><div class="small text-muted-2">{{ $dashboardMode->label() }} mode</div></div>
    <div class="d-flex gap-2">
        @if (Route::has('merchant.reports.export'))<a class="btn btn-outline-secondary" href="{{ route('merchant.reports.export', ['type' => 'payouts']) }}"><i class="bi bi-download"></i> Export CSV</a>@endif
        <a href="{{ route('merchant.payouts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Request payout</a>
    </div>
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="ID, reference or destination"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="st">Status</label>
            <select id="st" name="status" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\PayoutStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="cur">Currency</label><input id="cur" name="currency" value="{{ $filters['currency'] ?? '' }}" class="form-control form-control-sm text-uppercase"></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('merchant.payouts.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card">
        @if ($payouts->isEmpty())
            <x-empty-state icon="send" title="No payouts yet" text="Request a payout from your available balance." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Payout ID</th><th>Reference</th><th>Destination</th><th class="text-end">Amount</th><th class="text-end">Fees</th><th class="text-end">Total debit</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @foreach ($payouts as $po)
                    <tr>
                        <td class="font-mono"><a href="{{ route('merchant.payouts.show', $po) }}">{{ $po->public_id }}</a></td>
                        <td>{{ $po->merchant_reference }}</td>
                        <td class="small">{{ $po->provider->name }}<div class="font-mono text-muted-2">{{ $po->maskedDestination() }}</div></td>
                        <td class="text-end"><x-money :amount="$po->amount" :currency="$po->currency" /></td>
                        <td class="text-end"><x-money :amount="\App\Support\Decimal::add((string) $po->service_fee, (string) $po->network_fee)" /></td>
                        <td class="text-end"><x-money :amount="$po->total_debit" /></td>
                        <td><x-status-badge :status="$po->status" /></td>
                        <td class="small text-muted-2 text-nowrap">{{ $po->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $payouts->links() }}</div>
        @endif
    </div>
@endsection

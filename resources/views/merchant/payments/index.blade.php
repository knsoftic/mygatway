@extends('layouts.app', ['title' => 'Payments'])

@section('header')
    <div><h1 class="page-title">Payments</h1><div class="small text-muted-2">Pay In transactions · {{ $dashboardMode->label() }} mode</div></div>
    @if (Route::has('merchant.reports.export'))
        <a class="btn btn-outline-secondary" href="{{ route('merchant.reports.export', ['type' => 'payments'] + request()->query()) }}"><i class="bi bi-download"></i> Export CSV</a>
    @endif
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="ID, reference or tx hash"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="st">Status</label>
            <select id="st" name="status" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\PaymentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="cur">Currency</label><input id="cur" name="currency" value="{{ $filters['currency'] ?? '' }}" class="form-control form-control-sm text-uppercase"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="pr">Provider</label>
            <select id="pr" name="provider" class="form-select form-select-sm"><option value="">All</option>@foreach ($providers as $p)<option value="{{ $p->code }}" @selected(($filters['provider'] ?? '') === $p->code)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="fr">From</label><input id="fr" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('merchant.payments.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card">
        @if ($payments->isEmpty())
            <x-empty-state icon="receipt" title="No payments found" text="Payments created through the API appear here." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Payment ID</th><th>Reference</th><th>Provider</th><th class="text-end">Amount</th><th class="text-end">Received</th><th class="text-end">Fee</th><th class="text-end">Net</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td class="font-mono"><a href="{{ route('merchant.payments.show', $p) }}">{{ $p->public_id }}</a>@if ($p->needs_review) <i class="bi bi-flag-fill text-warning" title="Under review"></i>@endif</td>
                        <td>{{ $p->merchant_reference }}</td>
                        <td>{{ $p->provider->name }}</td>
                        <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
                        <td class="text-end"><x-money :amount="$p->received_amount" /></td>
                        <td class="text-end"><x-money :amount="$p->service_fee" /></td>
                        <td class="text-end"><x-money :amount="$p->merchant_net_amount" /></td>
                        <td><x-status-badge :status="$p->status" /></td>
                        <td class="small text-muted-2 text-nowrap">{{ $p->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection

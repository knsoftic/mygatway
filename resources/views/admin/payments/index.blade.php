@extends('layouts.app', ['title' => 'Pay In monitoring', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Pay In</h1><div class="small text-muted-2">{{ ucfirst($filters['mode']) }} payments across all merchants</div></div>
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Payment ID, reference, provider ID, tx hash"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="mode">Mode</label>
            <select id="mode" name="mode" class="form-select form-select-sm"><option value="live" @selected($filters['mode'] === 'live')>Live</option><option value="test" @selected($filters['mode'] === 'test')>Test</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="st">Status</label>
            <select id="st" name="status" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\PaymentStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="pr">Provider</label>
            <select id="pr" name="provider" class="form-select form-select-sm"><option value="">All</option>@foreach ($providers as $p)<option value="{{ $p->code }}" @selected(($filters['provider'] ?? '') === $p->code)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="fr">From</label><input id="fr" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-1" for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-1"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="review" value="1" id="rev" @checked($filters['review'] ?? false)><label class="form-check-label small" for="rev">Needs review</label></div></div>
        <div class="col-12 col-md-1 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payments.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card">
        @if ($payments->isEmpty())
            <x-empty-state icon="receipt" title="No payments found" text="Try a different filter or switch between live and test mode." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Payment ID</th><th>Merchant</th><th>Reference</th><th>Provider</th><th class="text-end">Amount</th><th class="text-end">Received</th><th class="text-end">Service fee</th><th>Status</th><th>Callback</th><th>Created</th></tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td class="font-mono small"><a href="{{ route('admin.payments.show', $p) }}">{{ $p->public_id }}</a>@if ($p->needs_review) <i class="bi bi-flag-fill text-warning" title="Needs review: {{ $p->review_reason }}"></i>@endif</td>
                        <td class="small">@can('merchants.view')<a href="{{ route('admin.merchants.show', $p->merchant) }}">{{ $p->merchant->name }}</a>@else{{ $p->merchant->name }}@endcan</td>
                        <td class="small">{{ $p->merchant_reference }}</td>
                        <td>{{ $p->provider->name }}</td>
                        <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
                        <td class="text-end"><x-money :amount="$p->received_amount" /></td>
                        <td class="text-end"><x-money :amount="$p->service_fee" /></td>
                        <td><x-status-badge :status="$p->status" /></td>
                        <td>@if ($p->callback_verified)<i class="bi bi-patch-check-fill text-success" title="Verified callback"></i>@else<span class="text-muted-2">—</span>@endif</td>
                        <td class="small text-muted-2 text-nowrap">{{ $p->created_at->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection

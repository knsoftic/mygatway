@extends('layouts.app', ['title' => 'Payouts', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Payouts</h1><div class="small text-muted-2">{{ ucfirst($filters['mode']) }} payouts · oldest first so the approval queue is worked in order</div></div>
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Payout ID, reference, destination, tx hash"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="mode">Mode</label>
            <select id="mode" name="mode" class="form-select form-select-sm"><option value="live" @selected($filters['mode'] === 'live')>Live</option><option value="test" @selected($filters['mode'] === 'test')>Test</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="st">Status</label>
            <select id="st" name="status" class="form-select form-select-sm"><option value="awaiting" @selected($filters['status'] === 'awaiting')>Awaiting approval</option>@foreach (\App\Enums\PayoutStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="review" value="1" id="rev" @checked($filters['review'] ?? false)><label class="form-check-label small" for="rev">Needs review</label></div></div>
        <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payouts.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card">
        @if ($payouts->isEmpty())
            <x-empty-state icon="send-check" title="{{ $filters['status'] === 'awaiting' ? 'No payouts waiting for approval' : 'No payouts found' }}" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Payout ID</th><th>Merchant</th><th>Provider</th><th>Destination</th><th class="text-end">Amount</th><th class="text-end">Total debit</th><th>Status</th><th>Requested</th><th></th></tr></thead>
                <tbody>
                @foreach ($payouts as $p)
                    <tr>
                        <td class="font-mono small"><a href="{{ route('admin.payouts.show', $p) }}">{{ $p->public_id }}</a>@if ($p->needs_review) <i class="bi bi-flag-fill text-warning" title="Needs review: {{ $p->review_reason }}"></i>@endif</td>
                        <td class="small">@can('merchants.view')<a href="{{ route('admin.merchants.show', $p->merchant) }}">{{ $p->merchant->name }}</a>@else{{ $p->merchant->name }}@endcan</td>
                        <td>{{ $p->provider->name }}</td>
                        <td class="small"><span class="text-muted-2">{{ $p->destination_type->label() }}</span><div class="font-mono">{{ $p->maskedDestination() }}</div></td>
                        <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
                        <td class="text-end"><x-money :amount="$p->total_debit" /></td>
                        <td><x-status-badge :status="$p->status" /></td>
                        <td class="small text-muted-2 text-nowrap">{{ $p->created_at->format('M j, H:i') }}<div>{{ $p->created_at->diffForHumans() }}</div></td>
                        <td class="text-end"><a href="{{ route('admin.payouts.show', $p) }}" class="btn btn-sm btn-outline-primary">{{ in_array($p->status->value, ['requested', 'under_review'], true) ? 'Review' : 'View' }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $payouts->links() }}</div>
        @endif
    </div>
@endsection

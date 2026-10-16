@extends('layouts.app', ['title' => 'Merchants', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Merchants</h1><div class="small text-muted-2">{{ number_format($merchants->total()) }} merchant account(s)</div></div>
    @can('merchants.manage')
        <a class="btn btn-primary" href="{{ route('admin.merchants.create') }}"><i class="bi bi-plus-lg"></i> New merchant</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-5"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Name, business email or merchant ID"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="st">Status</label>
            <select id="st" name="status" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\MerchantStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="ver">Verification</label>
            <select id="ver" name="verification" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\VerificationStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['verification'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.merchants.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card">
        @if ($merchants->isEmpty())
            <x-empty-state icon="shop" title="No merchants found" text="Merchants appear here after they sign up." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Merchant</th><th>Owner</th><th>Status</th><th>Verification</th><th>Live</th><th class="text-end">Live payments</th><th>Joined</th></tr></thead>
                <tbody>
                @foreach ($merchants as $m)
                    <tr>
                        <td><a href="{{ route('admin.merchants.show', $m) }}" class="fw-semibold">{{ $m->name }}</a><div class="small text-muted-2 font-mono">{{ $m->public_id }}</div></td>
                        <td class="small">{{ $m->owner?->name ?? '—' }}<div class="text-muted-2">{{ $m->business_email ?? $m->owner?->email }}</div></td>
                        <td><x-status-badge :status="$m->status" /></td>
                        <td><x-status-badge :status="$m->verification_status" /></td>
                        <td>@if ($m->live_enabled)<i class="bi bi-check-circle-fill text-success" title="Live enabled"></i>@else<span class="text-muted-2">—</span>@endif</td>
                        <td class="text-end">{{ number_format($m->live_payments_count) }}</td>
                        <td class="small text-muted-2 text-nowrap">{{ $m->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $merchants->links() }}</div>
        @endif
    </div>
@endsection

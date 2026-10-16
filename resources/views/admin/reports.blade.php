@extends('layouts.app', ['title' => 'Reports', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Reports</h1><div class="small text-muted-2">CSV exports for accounting and reconciliation. Every export is recorded in the audit log.</div></div>
@endsection

@section('content')
<div class="row g-4">
    @foreach ([
        'payments' => ['Pay In transactions', 'arrow-down-left-circle', 'Every payment with provider IDs, amounts received, service fee, provider fee and merchant net.'],
        'payouts' => ['Payouts', 'arrow-up-right-circle', 'Every payout with destination, fees, total debit and approver.'],
        'revenue' => ['Platform revenue (ledger)', 'cash-coin', 'Ledger entries on the platform fee revenue, network fee and provider fee expense accounts.'],
    ] as $type => [$label, $icon, $text])
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-{{ $icon }} fs-4 text-primary"></i><h2 class="h6 mb-0">{{ $label }}</h2></div>
                <p class="small text-muted-2">{{ $text }}</p>
                <form method="GET" action="{{ route('admin.reports.export') }}" class="row g-2 mt-auto">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="col-12"><label class="form-label small mb-1" for="m-{{ $type }}">Mode</label><select id="m-{{ $type }}" name="mode" class="form-select form-select-sm"><option value="live">Live</option><option value="test">Test</option></select></div>
                    <div class="col-6"><label class="form-label small mb-1" for="f-{{ $type }}">From</label><input id="f-{{ $type }}" type="date" name="from" required value="{{ now()->startOfMonth()->toDateString() }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small mb-1" for="t-{{ $type }}">To</label><input id="t-{{ $type }}" type="date" name="to" required value="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-download"></i> Download CSV</button></div>
                </form>
            </div></div>
        </div>
    @endforeach
</div>
@if ($errors->any())
    <div class="alert alert-danger mt-4 small">{{ $errors->first() }}</div>
@endif
@endsection

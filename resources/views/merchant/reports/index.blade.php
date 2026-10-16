@extends('layouts.app', ['title' => 'Reports'])

@section('header')
    <div><h1 class="page-title">Reports</h1><div class="small text-muted-2">Download CSV exports for accounting. {{ $dashboardMode->label() }} mode data only.</div></div>
@endsection

@section('content')
<div class="row g-4">
    @foreach (['payments' => ['arrow-down-left-circle', 'Payments', 'All Pay In transactions with received amounts, fees and net amounts.'], 'payouts' => ['arrow-up-right-circle', 'Payouts', 'All payouts with destinations, fees and total debits.'], 'ledger' => ['journal-text', 'Ledger', 'Every change to your available balance with running balances.']] as $type => [$icon, $label, $text])
        <div class="col-lg-4">
            <form method="GET" action="{{ route('merchant.reports.export') }}" class="card h-100">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="card-body">
                    <div class="feature-icon mb-3"><i class="bi bi-{{ $icon }}"></i></div>
                    <h2 class="h6 fw-bold">{{ $label }}</h2>
                    <p class="small text-muted-2">{{ $text }}</p>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small mb-1" for="{{ $type }}-from">From</label><input id="{{ $type }}-from" type="date" name="from" class="form-control form-control-sm" value="{{ now()->subDays(30)->toDateString() }}"></div>
                        <div class="col-6"><label class="form-label small mb-1" for="{{ $type }}-to">To</label><input id="{{ $type }}-to" type="date" name="to" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                        <div class="col-12"><label class="form-label small mb-1" for="{{ $type }}-cur">Currency (optional)</label><input id="{{ $type }}-cur" name="currency" class="form-control form-control-sm text-uppercase"></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-download"></i> Download CSV</button></div>
            </form>
        </div>
    @endforeach
</div>
@endsection

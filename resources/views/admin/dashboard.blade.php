@extends('layouts.app', ['title' => 'Admin overview', 'area' => 'admin'])

@section('header')
    <div>
        <h1 class="page-title">Platform overview</h1>
        <div class="small text-muted-2">{{ $mode->label() }} data · {{ $filters['from'] }} → {{ $filters['to'] }}</div>
    </div>
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm" aria-label="From">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm" aria-label="To">
        <select name="mode" class="form-select form-select-sm" aria-label="Mode"><option value="live" @selected($mode->value === 'live')>Live</option><option value="test" @selected($mode->value === 'test')>Test</option></select>
        <button class="btn btn-sm btn-primary">Apply</button>
    </form>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card label="Total merchants" icon="shop">{{ number_format($merchantCounts['total']) }}</x-stat-card></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Active merchants" icon="check2-circle" :hint="$merchantCounts['live'].' live-enabled'">{{ number_format($merchantCounts['active']) }}</x-stat-card></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Pending approvals" icon="patch-question">{{ number_format($merchantCounts['pending_approval']) }}</x-stat-card></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Payouts awaiting approval" icon="hourglass-split" :hint="$needsReview.' transactions need review'">{{ number_format($payoutsAwaiting) }}</x-stat-card></div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Volume &amp; transactions by currency</div>
        @if ($byCurrency->isEmpty())
            <x-empty-state icon="bar-chart" title="No transactions in this period" />
        @else
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Currency</th><th class="text-end">Pay In volume</th><th class="text-end">Pay Out volume</th><th class="text-end">Pay In fees</th><th class="text-end">Pay Out fees</th><th class="text-end">Provider fees</th><th class="text-end">Successful</th><th class="text-end">Pending</th><th class="text-end">Failed</th></tr></thead>
                <tbody>
                @foreach ($byCurrency as $cur => $r)
                    <tr><td class="fw-bold">{{ $cur }}</td>
                        <td class="text-end"><x-money :amount="$r['payin_volume']" /></td>
                        <td class="text-end"><x-money :amount="$r['payout_volume']" /></td>
                        <td class="text-end"><x-money :amount="$r['payin_fees']" /></td>
                        <td class="text-end"><x-money :amount="$r['payout_fees']" /></td>
                        <td class="text-end"><x-money :amount="\App\Support\Decimal::add($r['payin_provider_fees'], $r['payout_provider_fees'])" /></td>
                        <td class="text-end">{{ number_format($r['succeeded']) }}</td>
                        <td class="text-end">{{ number_format($r['pending']) }}</td>
                        <td class="text-end">{{ number_format($r['failed']) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">Platform revenue (ledger, all time)</div>
                @if ($revenue->isEmpty())
                    <x-empty-state icon="cash-coin" title="No revenue recorded yet" />
                @else
                    <div class="table-responsive"><table class="table">
                        <thead><tr><th>Currency</th><th class="text-end">Service fees</th><th class="text-end">Network fee recovery</th><th class="text-end">Provider fees (cost)</th><th class="text-end">Est. net revenue</th></tr></thead>
                        <tbody>
                        @foreach ($revenue as $cur => $r)
                            <tr><td class="fw-bold">{{ $cur }}</td>
                                <td class="text-end"><x-money :amount="$r['service']" /></td>
                                <td class="text-end"><x-money :amount="$r['network']" /></td>
                                <td class="text-end text-danger"><x-money :amount="$r['provider']" /></td>
                                <td class="text-end fw-bold"><x-money :amount="$r['net']" /></td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">Provider integration health</div>
                <ul class="list-group list-group-flush">
                    @foreach ($providers as $p)
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <div><div class="fw-semibold">{{ $p->name }}</div>
                                <div class="small text-muted-2">{{ $p->health_message ?? 'Not checked yet' }}@if ($p->health_checked_at) · {{ $p->health_checked_at->diffForHumans() }}@endif</div></div>
                            <span class="badge badge-soft text-bg-{{ match ($p->health_status) { 'healthy' => 'success', 'degraded' => 'warning', 'down' => 'danger', default => 'secondary' } }}">{{ ucfirst($p->health_status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <div class="card-header">Open risk events</div>
                @if ($risk->isEmpty())
                    <x-empty-state icon="shield-check" title="No open risk events" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($risk as $e)
                            <li class="list-group-item bg-transparent d-flex justify-content-between"><span class="small"><span class="font-mono">{{ $e->type }}</span><div class="text-muted-2">{{ $e->created_at->diffForHumans() }}</div></span><span class="badge badge-soft text-bg-{{ $e->badge() }} align-self-center">{{ ucfirst($e->severity) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

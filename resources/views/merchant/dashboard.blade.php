@extends('layouts.app', ['title' => 'Dashboard'])

@section('header')
    <div>
        <h1 class="page-title">Dashboard</h1>
        <div class="small text-muted-2">{{ $mode->label() }} mode · {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('M j, Y') }}</div>
    </div>
    <div class="d-flex gap-2">
        @if (Route::has('merchant.payouts.create'))
            <a href="{{ route('merchant.payouts.create') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-up-right"></i> Request payout</a>
        @endif
        <a href="{{ route('merchant.api-keys.index') }}" class="btn btn-primary"><i class="bi bi-key"></i> API keys</a>
    </div>
@endsection

@section('content')
    @if (! $merchant->isLiveEnabled())
        <div class="alert alert-info d-flex flex-wrap gap-3 align-items-center">
            <i class="bi bi-patch-check fs-4"></i>
            <div class="flex-grow-1">
                <strong>Live payments are not enabled yet.</strong>
                <div class="small">You can integrate and test everything in test mode now. Submit your business verification to go live.</div>
            </div>
            @if (Route::has('merchant.verification.show'))
                <a class="btn btn-sm btn-primary" href="{{ route('merchant.verification.show') }}">Start verification</a>
            @endif
        </div>
    @endif

    @if (! $hasApiKeys)
        <div class="card mb-4 border-primary-subtle"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <span class="stat-icon"><i class="bi bi-rocket-takeoff"></i></span>
            <div class="flex-grow-1"><strong>Get started:</strong> create a test API key and make your first payment request.</div>
            <a class="btn btn-sm btn-primary" href="{{ route('merchant.api-keys.index') }}">Create API key</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('docs.index') }}">Read the docs</a>
        </div></div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="card mb-4"><div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f-from">From</label><input id="f-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f-to">To</label><input id="f-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f-cur">Currency</label>
            <input id="f-cur" name="currency" value="{{ $filters['currency'] ?? '' }}" placeholder="All" class="form-control form-control-sm text-uppercase" maxlength="16"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f-prov">Provider</label>
            <select id="f-prov" name="provider" class="form-select form-select-sm"><option value="">All</option>
                @foreach ($providers as $p)<option value="{{ $p->code }}" @selected(($filters['provider'] ?? '') === $p->code)>{{ $p->name }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f-status">Pay In status</label>
            <select id="f-status" name="status" class="form-select form-select-sm"><option value="">All</option>
                @foreach (['settled' => 'Successful', 'open' => 'Pending', 'unsuccessful' => 'Failed / expired'] as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Apply</button><a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary" title="Reset filters"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    {{-- Balances (ledger) --}}
    <h2 class="h6 fw-bold text-uppercase text-muted-2 mb-3">Balances</h2>
    @if (empty($balances) && $pendingBalances->isEmpty())
        <div class="card mb-4"><x-empty-state icon="wallet2" title="No balance yet" text="Balances appear here once a payment is confirmed by the provider." /></div>
    @else
        <div class="row g-3 mb-4">
            @foreach (collect(array_keys($balances))->merge($pendingBalances->keys())->unique()->sort() as $cur)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold">{{ $cur }}</span><span class="stat-icon"><i class="bi bi-wallet2"></i></span></div>
                        <div class="stat-card"><div class="stat-label">Available / withdrawable</div><div class="stat-value"><x-money :amount="$balances[$cur]['available'] ?? '0'" /></div></div>
                        <div class="d-flex justify-content-between small mt-2 text-muted-2">
                            <span>Reserved (payouts): <x-money :amount="$balances[$cur]['reserved'] ?? '0'" class="text-body" /></span>
                            <span>Pending: <x-money :amount="$pendingBalances[$cur] ?? '0'" class="text-body" /></span>
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Per-currency activity --}}
    <h2 class="h6 fw-bold text-uppercase text-muted-2 mb-3">Activity in period</h2>
    @forelse ($byCurrency as $cur => $row)
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-2"><x-stat-card :label="'Pay In · '.$cur" icon="arrow-down-left"><x-money :amount="$row['payin_volume']" /></x-stat-card></div>
            <div class="col-6 col-xl-2"><x-stat-card :label="'Pay Out · '.$cur" icon="arrow-up-right"><x-money :amount="$row['payout_volume']" /></x-stat-card></div>
            <div class="col-6 col-xl-2"><x-stat-card :label="'Pay In fees · '.$cur" icon="percent"><x-money :amount="$row['payin_fees']" /></x-stat-card></div>
            <div class="col-6 col-xl-2"><x-stat-card :label="'Pay Out fees · '.$cur" icon="percent"><x-money :amount="$row['payout_fees']" /></x-stat-card></div>
            <div class="col-4 col-xl-1"><x-stat-card label="Success" icon="check2-circle">{{ number_format($row['succeeded']) }}</x-stat-card></div>
            <div class="col-4 col-xl-1"><x-stat-card label="Pending" icon="hourglass-split">{{ number_format($row['pending']) }}</x-stat-card></div>
            <div class="col-4 col-xl-2"><x-stat-card label="Failed" icon="x-circle">{{ number_format($row['failed']) }}</x-stat-card></div>
        </div>
    @empty
        <div class="card mb-4"><x-empty-state icon="bar-chart" title="No transactions in this period" text="Adjust the filters or create a payment through the API." /></div>
    @endforelse

    {{-- Charts --}}
    <div class="card my-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Volume · {{ $chartCurrency }}</span>
            <ul class="nav nav-pills nav-sm small" role="tablist">
                @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $l)
                    <li class="nav-item"><button class="nav-link py-1 px-2 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#chart-{{ $k }}" type="button" role="tab">{{ $l }}</button></li>
                @endforeach
            </ul>
        </div>
        <div class="card-body tab-content">
            @foreach ($charts as $k => $series)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="chart-{{ $k }}" role="tabpanel">
                    <div class="chart-box"><canvas data-chart="chart-data-{{ $k }}" data-chart-type="{{ $k === 'daily' ? 'line' : 'bar' }}" aria-label="{{ ucfirst($k) }} volume chart" role="img"></canvas></div>
                    <script type="application/json" id="chart-data-{{ $k }}">@json($series)</script>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent transactions --}}
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><span>Recent payments</span>@if (Route::has('merchant.payments.index'))<a href="{{ route('merchant.payments.index') }}" class="small">View all</a>@endif</div>
                @if ($recentPayments->isEmpty())
                    <x-empty-state icon="receipt" title="No payments yet" />
                @else
                    <div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>Payment</th><th>Reference</th><th>Provider</th><th class="text-end">Amount</th><th>Status</th><th>Created</th></tr></thead>
                        <tbody>
                        @foreach ($recentPayments as $p)
                            <tr>
                                <td class="font-mono">@if (Route::has('merchant.payments.show'))<a href="{{ route('merchant.payments.show', $p) }}">{{ \Illuminate\Support\Str::limit($p->public_id, 16) }}</a>@else{{ \Illuminate\Support\Str::limit($p->public_id, 16) }}@endif</td>
                                <td>{{ $p->merchant_reference }}</td>
                                <td>{{ $p->provider->name }}</td>
                                <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
                                <td><x-status-badge :status="$p->status" /></td>
                                <td class="small text-muted-2">{{ $p->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><span>Recent payouts</span>@if (Route::has('merchant.payouts.index'))<a href="{{ route('merchant.payouts.index') }}" class="small">View all</a>@endif</div>
                @if ($recentPayouts->isEmpty())
                    <x-empty-state icon="send" title="No payouts yet" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($recentPayouts as $po)
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                <div>
                                    <div class="fw-semibold"><x-money :amount="$po->amount" :currency="$po->currency" /></div>
                                    <div class="small text-muted-2">{{ $po->maskedDestination() }}</div>
                                </div>
                                <x-status-badge :status="$po->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

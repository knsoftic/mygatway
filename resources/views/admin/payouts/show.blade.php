@extends('layouts.app', ['title' => 'Payout '.$payout->public_id, 'area' => 'admin'])

@php $awaiting = in_array($payout->status, [\App\Enums\PayoutStatus::Requested, \App\Enums\PayoutStatus::UnderReview], true); @endphp

@section('header')
    <div>
        <a href="{{ route('admin.payouts.index', ['mode' => $payout->mode->value]) }}" class="small"><i class="bi bi-arrow-left"></i> Payouts</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap"><x-money :amount="$payout->amount" :currency="$payout->currency" /> <x-status-badge :status="$payout->status" />
            @if ($payout->mode->value === 'test')<span class="badge badge-soft text-bg-warning">Test</span>@endif</h1>
        <div class="small text-muted-2 font-mono">{{ $payout->public_id }}</div>
    </div>
    @can('payouts.approve')
        @if ($payout->status === \App\Enums\PayoutStatus::Processing && $payout->mode->value === 'live')
            <form method="POST" action="{{ route('admin.payouts.reverify', $payout) }}">@csrf
                <button class="btn btn-outline-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-arrow-repeat"></i> Re-verify with provider</span></button>
            </form>
        @endif
    @endcan
@endsection

@section('content')
    @if ($payout->needs_review)
        <div class="alert alert-warning">
            <div><i class="bi bi-flag-fill"></i> Flagged for review: <strong>{{ $payout->review_reason }}</strong></div>
            @can('payouts.approve')
                <form method="POST" action="{{ route('admin.payouts.clear-review', $payout) }}" class="row g-2 mt-1 align-items-end">@csrf
                    <div class="col-md-9"><label class="form-label small mb-1" for="cnote">Resolution note</label><input id="cnote" name="note" required maxlength="1000" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-warning w-100">Clear review flag</button></div>
                </form>
            @endcan
        </div>
    @endif
    @if ($awaiting && ! $providerConfigured)
        <div class="alert alert-danger small"><i class="bi bi-plug"></i> {{ $payout->provider->name }} credentials are not configured. An approved payout cannot be submitted until they are.</div>
    @endif
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Details</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted-2 fw-normal">Merchant</dt><dd class="col-sm-8">@can('merchants.view')<a href="{{ route('admin.merchants.show', $payout->merchant) }}">{{ $payout->merchant->name }}</a>@else{{ $payout->merchant->name }}@endcan <span class="font-mono text-muted-2">{{ $payout->merchant->public_id }}</span></dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Reference</dt><dd class="col-sm-8">{{ $payout->merchant_reference }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider</dt><dd class="col-sm-8">{{ $payout->provider->name }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Destination</dt><dd class="col-sm-8"><span class="text-muted-2">{{ $payout->destination_type->label() }}:</span> <span class="font-mono text-break">{{ $payout->destination }}</span>@if ($payout->destination_memo)<div>Memo: {{ $payout->destination_memo }}</div>@endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Network</dt><dd class="col-sm-8">{{ $payout->network ? strtoupper($payout->network) : '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider order ID</dt><dd class="col-sm-8 font-mono text-break">{{ $payout->provider_order_id ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider payout ID</dt><dd class="col-sm-8 font-mono text-break">{{ $payout->provider_payout_id ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider status</dt><dd class="col-sm-8 font-mono">{{ $payout->provider_status ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Transaction hash</dt><dd class="col-sm-8 font-mono text-break">{{ $payout->tx_hash ?? '—' }}</dd>
                @if ($payout->failure_reason)<dt class="col-sm-4 text-muted-2 fw-normal">Failure reason</dt><dd class="col-sm-8 text-danger">{{ $payout->failure_reason }}</dd>@endif
                @if ($payout->rejection_reason)<dt class="col-sm-4 text-muted-2 fw-normal">Rejection reason</dt><dd class="col-sm-8 text-danger">{{ $payout->rejection_reason }}</dd>@endif
                <dt class="col-sm-4 text-muted-2 fw-normal">Description</dt><dd class="col-sm-8">{{ $payout->description ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Requested</dt><dd class="col-sm-8">{{ $payout->created_at->toDayDateTimeString() }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Submitted</dt><dd class="col-sm-8">{{ $payout->submitted_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Completed</dt><dd class="col-sm-8">{{ $payout->completed_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div></div>

        <div class="card mb-4"><div class="card-header">Approval history</div>
            @if ($payout->approvals->isEmpty())
                <div class="card-body small text-muted-2">No actions recorded.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($payout->approvals as $a)
                        <li class="list-group-item bg-transparent d-flex justify-content-between">
                            <div class="small"><span class="fw-semibold">{{ ucfirst($a->action) }}</span>@if ($a->from_status) {{ str_replace('_', ' ', $a->from_status) }}@endif @if ($a->to_status) → {{ str_replace('_', ' ', $a->to_status) }}@endif
                                @if ($a->note)<div class="text-muted-2">{{ $a->note }}</div>@endif</div>
                            <span class="small text-muted-2 text-end">{{ $a->user?->name ?? 'System' }}@if ($a->ip_address) · {{ $a->ip_address }}@endif<div>{{ $a->created_at->format('M j, H:i') }}</div></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card"><div class="card-header">Previous payouts to this destination</div>
            @if ($previousPayouts->isEmpty())
                <div class="card-body small"><i class="bi bi-exclamation-triangle text-warning"></i> First payout from this merchant to this destination.</div>
            @else
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <tbody>@foreach ($previousPayouts as $pp)
                        <tr><td class="font-mono small"><a href="{{ route('admin.payouts.show', $pp) }}">{{ $pp->public_id }}</a></td><td class="text-end"><x-money :amount="$pp->amount" :currency="$pp->currency" /></td>
                            <td><x-status-badge :status="$pp->status" /></td><td class="small text-muted-2">{{ $pp->created_at->format('M j, Y') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header">Amounts</div><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><td>Amount to recipient</td><td class="text-end"><x-money :amount="$payout->amount" :currency="$payout->currency" /></td></tr>
                <tr><td>Platform service fee</td><td class="text-end">+ <x-money :amount="$payout->service_fee" /></td></tr>
                <tr><td>Network fee</td><td class="text-end">+ <x-money :amount="$payout->network_fee" /></td></tr>
                <tr class="fw-bold"><td>Total debit</td><td class="text-end"><x-money :amount="$payout->total_debit" :currency="$payout->currency" /></td></tr>
                <tr><td class="text-muted-2">Provider fee (cost)</td><td class="text-end text-muted-2"><x-money :amount="$payout->provider_fee" /></td></tr>
            </table>
            <div class="small mt-3"><span class="text-muted-2">Merchant {{ $payout->currency }} balance:</span>
                @if ($merchantBalance) available <x-money :amount="$merchantBalance['available']" /> · reserved <x-money :amount="$merchantBalance['reserved']" />@else none @endif</div>
        </div></div>

        @can('payouts.approve')
            @if ($awaiting)
                <div class="card mb-4"><div class="card-header">Approve</div><div class="card-body">
                    <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}" data-confirm="Approve and submit this payout to {{ $payout->provider->name }}?">
                        @csrf
                        <div class="mb-2"><label class="form-label small mb-1" for="anote">Note <span class="text-muted-2">(optional)</span></label><textarea id="anote" name="note" rows="2" maxlength="1000" class="form-control form-control-sm"></textarea></div>
                        <div class="form-check mb-3"><input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="aconfirm" required><label class="form-check-label small" for="aconfirm">I checked the destination and amount</label></div>
                        <button class="btn btn-success" data-loading @disabled(! $providerConfigured)><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-check2-circle"></i> Approve payout</span></button>
                    </form>
                </div></div>
                <div class="card mb-4"><div class="card-header">Reject</div><div class="card-body">
                    <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}" data-confirm="Reject this payout and release the reserved funds?">
                        @csrf
                        <div class="mb-2"><label class="form-label small mb-1" for="rreason">Reason shown to the merchant</label><input id="rreason" name="reason" required maxlength="255" class="form-control form-control-sm @error('reason') is-invalid @enderror" value="{{ old('reason') }}">@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <button class="btn btn-outline-danger" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-x-circle"></i> Reject payout</span></button>
                    </form>
                </div></div>
            @endif
        @endcan

        <div class="card"><div class="card-header">Ledger entries</div>
            @php $entries = $payout->ledgerTransactions->flatMap(fn ($t) => $t->entries->map(fn ($e) => [$t, $e])); @endphp
            @if ($entries->isEmpty())
                <x-empty-state icon="journal" title="No ledger entries" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($entries as [$t, $e])
                        <li class="list-group-item bg-transparent d-flex justify-content-between">
                            <span class="small">{{ \App\Models\Transaction::typeLabel($t->type) }}<div class="text-muted-2">{{ $e->account->type->label() }}@if ($e->account->merchant_id) · merchant @endif · {{ $e->account->currency }}</div></span>
                            <span class="amount {{ $e->direction->value === 'credit' ? 'text-success' : 'text-danger' }}">{{ $e->direction->value === 'credit' ? 'CR' : 'DR' }} {{ \App\Support\Decimal::format($e->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection

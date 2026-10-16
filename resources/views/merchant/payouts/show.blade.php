@extends('layouts.app', ['title' => 'Payout '.$payout->public_id])

@section('header')
    <div>
        <a href="{{ route('merchant.payouts.index') }}" class="small"><i class="bi bi-arrow-left"></i> Payouts</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap"><x-money :amount="$payout->amount" :currency="$payout->currency" /> <x-status-badge :status="$payout->status" /></h1>
        <div class="small text-muted-2 font-mono">{{ $payout->public_id }}</div>
    </div>
    @can('cancel', $payout)
        <form method="POST" action="{{ route('merchant.payouts.cancel', $payout) }}" data-confirm="Cancel this payout and release the reserved funds?">@csrf<button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Cancel payout</button></form>
    @endcan
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Details</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted-2 fw-normal">Reference</dt><dd class="col-sm-8">{{ $payout->merchant_reference }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider</dt><dd class="col-sm-8">{{ $payout->provider->name }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Destination</dt><dd class="col-sm-8"><span class="text-muted-2">{{ $payout->destination_type->label() }}:</span> <span class="font-mono text-break">{{ $payout->destination }}</span>@if ($payout->destination_memo)<div>Memo: {{ $payout->destination_memo }}</div>@endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Network</dt><dd class="col-sm-8">{{ $payout->network ? strtoupper($payout->network) : '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Transaction</dt><dd class="col-sm-8 font-mono text-break">{{ $payout->tx_hash ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider status</dt><dd class="col-sm-8 font-mono">{{ $payout->provider_status ?? '—' }}</dd>
                @if ($payout->failure_reason)<dt class="col-sm-4 text-muted-2 fw-normal">Failure reason</dt><dd class="col-sm-8 text-danger">{{ $payout->failure_reason }}</dd>@endif
                @if ($payout->rejection_reason)<dt class="col-sm-4 text-muted-2 fw-normal">Rejection reason</dt><dd class="col-sm-8 text-danger">{{ $payout->rejection_reason }}</dd>@endif
                <dt class="col-sm-4 text-muted-2 fw-normal">Description</dt><dd class="col-sm-8">{{ $payout->description ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Requested</dt><dd class="col-sm-8">{{ $payout->created_at->toDayDateTimeString() }}</dd>
            </dl>
        </div></div>
        <div class="card"><div class="card-header">History</div>
            <ul class="list-group list-group-flush">
                @foreach ($payout->approvals as $a)
                    <li class="list-group-item bg-transparent d-flex justify-content-between">
                        <div class="small"><span class="fw-semibold">{{ ucfirst($a->action) }}</span>@if ($a->to_status) → {{ str_replace('_', ' ', $a->to_status) }}@endif
                            @if ($a->note)<div class="text-muted-2">{{ $a->note }}</div>@endif</div>
                        <span class="small text-muted-2">{{ $a->user ? ($a->user->is_admin ? 'Platform team' : $a->user->name) : 'System' }} · {{ $a->created_at->format('M j, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-header">Amounts</div><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><td>Amount to recipient</td><td class="text-end"><x-money :amount="$payout->amount" :currency="$payout->currency" /></td></tr>
                <tr><td>Service fee</td><td class="text-end">+ <x-money :amount="$payout->service_fee" /></td></tr>
                <tr><td>Network fee (provider estimate)</td><td class="text-end">+ <x-money :amount="$payout->network_fee" /></td></tr>
                <tr class="fw-bold"><td>Total debited</td><td class="text-end"><x-money :amount="$payout->total_debit" :currency="$payout->currency" /></td></tr>
            </table>
            <div class="small text-muted-2 mt-2">@if ($payout->status->holdsFunds()) This amount is currently reserved from your balance. @elseif ($payout->status->value === 'completed') Settled. @else Released back to your available balance. @endif</div>
        </div></div>
    </div>
</div>
@endsection

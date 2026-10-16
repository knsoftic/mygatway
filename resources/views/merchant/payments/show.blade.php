@extends('layouts.app', ['title' => 'Payment '.$payment->public_id])

@section('header')
    <div>
        <a href="{{ route('merchant.payments.index') }}" class="small"><i class="bi bi-arrow-left"></i> Payments</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap"><x-money :amount="$payment->amount" :currency="$payment->currency" /> <x-status-badge :status="$payment->status" /></h1>
        <div class="small text-muted-2 font-mono">{{ $payment->public_id }}</div>
    </div>
@endsection

@section('content')
    @if ($payment->needs_review)
        <div class="alert alert-warning"><i class="bi bi-flag-fill"></i> This payment is flagged for review by our team: <strong>{{ $payment->review_reason }}</strong>.</div>
    @endif
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Details</div><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-muted-2 fw-normal">Reference</dt><dd class="col-sm-8">{{ $payment->merchant_reference }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Description</dt><dd class="col-sm-8">{{ $payment->description ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider</dt><dd class="col-sm-8">{{ $payment->provider->name }} @if ($payment->mode->value === 'test')<span class="badge badge-soft text-bg-warning">Sandbox</span>@endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider status</dt><dd class="col-sm-8 font-mono">{{ $payment->provider_status ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Network</dt><dd class="col-sm-8">{{ $payment->network ? strtoupper($payment->network) : 'Payer choice' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Pay address</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->pay_address ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Payer address</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->payer_address ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Transaction hash</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->tx_hash ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Customer email</dt><dd class="col-sm-8">{{ $payment->customer_email ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Checkout URL</dt><dd class="col-sm-8 text-break">@if ($payment->checkout_url && $payment->status->isOpen())<a href="{{ $payment->checkout_url }}" target="_blank" rel="noopener noreferrer">Open checkout <i class="bi bi-box-arrow-up-right"></i></a>@else — @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Callback verified</dt><dd class="col-sm-8">@if ($payment->callback_verified)<i class="bi bi-patch-check-fill text-success"></i> {{ $payment->callback_verified_at?->toDayDateTimeString() }}@elseif ($payment->mode->value === 'test') Sandbox (test mode) @else No @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Created</dt><dd class="col-sm-8">{{ $payment->created_at->toDayDateTimeString() }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Expires</dt><dd class="col-sm-8">{{ $payment->expires_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Completed</dt><dd class="col-sm-8">{{ $payment->completed_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
            @if ($payment->metadata)
                <div class="mt-3"><div class="small fw-semibold mb-1">Metadata</div><pre class="bg-body-tertiary rounded p-2 small mb-0">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></div>
            @endif
        </div></div>

        <div class="card"><div class="card-header">Webhook events</div>
            @if ($events->isEmpty())
                <x-empty-state icon="broadcast" title="No events yet" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($events as $e)
                        <li class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between"><span class="font-mono small">{{ $e->type }}</span><span class="small text-muted-2">{{ $e->created_at->format('M j, H:i:s') }}</span></div>
                            @forelse ($e->deliveries as $d)
                                <div class="small"><span class="badge badge-soft text-bg-{{ $d->badge() }}">{{ ucfirst($d->status) }}</span> {{ \Illuminate\Support\Str::limit($d->endpoint->url, 50) }} · {{ $d->attempts }} attempt(s)</div>
                            @empty
                                <div class="small text-muted-2">No endpoint subscribed.</div>
                            @endforelse
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header">Amounts</div><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><td>Requested</td><td class="text-end"><x-money :amount="$payment->amount" :currency="$payment->currency" /></td></tr>
                <tr><td>Received</td><td class="text-end"><x-money :amount="$payment->received_amount" :currency="$payment->received_currency" /></td></tr>
                <tr><td>Service fee {{ $payment->status->isSettled() ? '' : '(estimate)' }}</td><td class="text-end">− <x-money :amount="$payment->service_fee" /></td></tr>
                <tr class="fw-bold"><td>Net to balance</td><td class="text-end"><x-money :amount="$payment->merchant_net_amount" :currency="$payment->currency" /></td></tr>
            </table>
            @php $final = $payment->fees->firstWhere('stage', 'final') ?? $payment->fees->firstWhere('stage', 'quote'); @endphp
            @if ($final)
                <div class="small text-muted-2 mt-2">Fee rule: {{ rtrim(rtrim($final->percentage, '0'), '.') }}% + {{ \App\Support\Decimal::format($final->fixed_amount) }}@if ($final->min_fee) · min {{ $final->min_fee }}@endif @if ($final->max_fee) · max {{ $final->max_fee }}@endif (frozen at {{ $final->stage === 'final' ? 'settlement' : 'creation' }})</div>
            @endif
        </div></div>

        <div class="card"><div class="card-header">Ledger entries</div>
            @php $entries = $payment->ledgerTransactions->flatMap(fn ($t) => $t->entries->filter(fn ($e) => $e->account->merchant_id === $payment->merchant_id)->map(fn ($e) => [$t, $e])); @endphp
            @if ($entries->isEmpty())
                <x-empty-state icon="journal" title="Not settled yet" text="Entries are posted when the provider confirms the payment." />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($entries as [$t, $e])
                        <li class="list-group-item bg-transparent d-flex justify-content-between">
                            <span class="small">{{ \App\Models\Transaction::typeLabel($t->type) }}<div class="text-muted-2">{{ $e->account->type->label() }}</div></span>
                            <span class="amount {{ $e->direction->value === 'credit' ? 'text-success' : 'text-danger' }}">{{ $e->direction->value === 'credit' ? '+' : '−' }}{{ \App\Support\Decimal::format($e->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection

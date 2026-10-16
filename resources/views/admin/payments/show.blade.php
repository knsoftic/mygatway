@extends('layouts.app', ['title' => 'Payment '.$payment->public_id, 'area' => 'admin'])

@section('header')
    <div>
        <a href="{{ route('admin.payments.index', ['mode' => $payment->mode->value]) }}" class="small"><i class="bi bi-arrow-left"></i> Pay In</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap"><x-money :amount="$payment->amount" :currency="$payment->currency" /> <x-status-badge :status="$payment->status" />
            @if ($payment->mode->value === 'test')<span class="badge badge-soft text-bg-warning">Test</span>@endif</h1>
        <div class="small text-muted-2 font-mono">{{ $payment->public_id }}</div>
    </div>
    @can('payments.reconcile')
        <form method="POST" action="{{ route('admin.payments.reverify', $payment) }}">@csrf
            <button class="btn btn-outline-primary" data-loading @disabled($payment->mode->value !== 'live')><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-arrow-repeat"></i> Re-verify with provider</span></button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="alert alert-info small"><i class="bi bi-shield-lock"></i> Payment status can only change through verified provider data (callback, re-verification or reconciliation). It cannot be set manually.</div>

    @if ($payment->needs_review)
        <div class="alert alert-warning">
            <div><i class="bi bi-flag-fill"></i> Flagged for review: <strong>{{ $payment->review_reason }}</strong></div>
            @can('payments.reconcile')
                <form method="POST" action="{{ route('admin.payments.clear-review', $payment) }}" class="row g-2 mt-1 align-items-end">@csrf
                    <div class="col-md-9"><label class="form-label small mb-1" for="cnote">Resolution note</label><input id="cnote" name="note" required maxlength="1000" class="form-control form-control-sm @error('note') is-invalid @enderror">@error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><button class="btn btn-sm btn-warning w-100">Clear review flag</button></div>
                </form>
            @endcan
        </div>
    @endif
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Details</div><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-muted-2 fw-normal">Merchant</dt><dd class="col-sm-8">@can('merchants.view')<a href="{{ route('admin.merchants.show', $payment->merchant) }}">{{ $payment->merchant->name }}</a>@else{{ $payment->merchant->name }}@endcan <span class="font-mono text-muted-2">{{ $payment->merchant->public_id }}</span></dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Reference</dt><dd class="col-sm-8">{{ $payment->merchant_reference }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Description</dt><dd class="col-sm-8">{{ $payment->description ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">API key</dt><dd class="col-sm-8 font-mono">{{ $payment->apiKey?->key_id ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider</dt><dd class="col-sm-8">{{ $payment->provider->name }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider status</dt><dd class="col-sm-8 font-mono">{{ $payment->provider_status ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider order ID</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->provider_order_id ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Provider payment ID</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->provider_payment_id ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Network</dt><dd class="col-sm-8">{{ $payment->network ? strtoupper($payment->network) : 'Payer choice' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Pay address</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->pay_address ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Payer address</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->payer_address ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Transaction hash</dt><dd class="col-sm-8 font-mono text-break">{{ $payment->tx_hash ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Customer email</dt><dd class="col-sm-8">{{ $payment->customer_email ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Callback verified</dt><dd class="col-sm-8">@if ($payment->callback_verified)<i class="bi bi-patch-check-fill text-success"></i> {{ $payment->callback_verified_at?->toDayDateTimeString() }}@else No @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Created</dt><dd class="col-sm-8">{{ $payment->created_at->toDayDateTimeString() }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Expires</dt><dd class="col-sm-8">{{ $payment->expires_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Completed</dt><dd class="col-sm-8">{{ $payment->completed_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Failed</dt><dd class="col-sm-8">{{ $payment->failed_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Last synced</dt><dd class="col-sm-8">{{ $payment->last_synced_at?->diffForHumans() ?? 'Never' }}</dd>
            </dl>
            @if ($payment->metadata)
                <div class="mt-3"><div class="small fw-semibold mb-1">Metadata</div><pre class="bg-body-tertiary rounded p-2 small mb-0">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></div>
            @endif
        </div></div>

        <div class="card mb-4"><div class="card-header">Provider callbacks</div>
            @if ($providerLogs->isEmpty())
                <x-empty-state icon="inbox" title="No callbacks received" />
            @else
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Received</th><th>Event</th><th>Signature</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach ($providerLogs as $log)
                        <tr><td class="small text-nowrap">{{ $log->created_at->format('M j, H:i:s') }}</td><td class="font-mono small">{{ $log->event_type ?? '—' }}</td>
                            <td>@if ($log->signature_valid)<span class="badge badge-soft text-bg-success">Valid</span>@else<span class="badge badge-soft text-bg-danger">Invalid</span>@endif</td>
                            <td class="small">{{ ucfirst($log->status) }}</td>
                            <td class="text-end">@if (Route::has('admin.webhooks.inbound'))<a href="{{ route('admin.webhooks.inbound', $log) }}" class="small">Open</a>@endif</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>

        <div class="card"><div class="card-header">Merchant webhook events</div>
            @if ($events->isEmpty())
                <x-empty-state icon="broadcast" title="No events yet" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($events as $e)
                        <li class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between"><span class="font-mono small">{{ $e->type }}</span><span class="small text-muted-2">{{ $e->created_at->format('M j, H:i:s') }}</span></div>
                            @forelse ($e->deliveries as $d)
                                <div class="small d-flex justify-content-between align-items-center mt-1">
                                    <span><span class="badge badge-soft text-bg-{{ $d->badge() }}">{{ ucfirst($d->status) }}</span> {{ \Illuminate\Support\Str::limit($d->endpoint->url, 50) }} · {{ $d->attempts }} attempt(s)</span>
                                    @if ($d->status === \App\Models\WebhookDelivery::FAILED)
                                        @can('webhooks.retry')<form method="POST" action="{{ route('admin.webhooks.retry', $d) }}">@csrf<button class="btn btn-sm btn-outline-secondary py-0">Retry</button></form>@endcan
                                    @endif
                                </div>
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
                <tr><td>Platform service fee</td><td class="text-end"><x-money :amount="$payment->service_fee" /></td></tr>
                <tr><td>Provider fee (cost)</td><td class="text-end"><x-money :amount="$payment->provider_fee" /></td></tr>
                <tr><td>Network fee</td><td class="text-end"><x-money :amount="$payment->network_fee" /></td></tr>
                <tr class="fw-bold"><td>Merchant net</td><td class="text-end"><x-money :amount="$payment->merchant_net_amount" :currency="$payment->currency" /></td></tr>
            </table>
        </div></div>

        <div class="card mb-4"><div class="card-header">Fee snapshots</div>
            @if ($payment->fees->isEmpty())
                <div class="card-body small text-muted-2">No fee recorded.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($payment->fees as $fee)
                        <li class="list-group-item bg-transparent small">
                            <div class="d-flex justify-content-between"><span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $fee->kind)) }} · {{ $fee->stage }}</span><x-money :amount="$fee->amount" :currency="$fee->currency" /></div>
                            <div class="text-muted-2">{{ rtrim(rtrim((string) $fee->percentage, '0'), '.') ?: '0' }}% + {{ \App\Support\Decimal::format($fee->fixed_amount) }} on {{ \App\Support\Decimal::format($fee->base_amount) }}@if ($fee->min_fee) · min {{ $fee->min_fee }}@endif @if ($fee->max_fee) · max {{ $fee->max_fee }}@endif · rule {{ $fee->rule?->name ?? '#'.$fee->fee_rule_id }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card mb-4"><div class="card-header">Refunds</div>
            @if ($payment->refunds->isEmpty())
                <div class="card-body small text-muted-2">No refunds.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($payment->refunds as $r)
                        <li class="list-group-item bg-transparent small d-flex justify-content-between">
                            <span><span class="font-mono">{{ $r->public_id }}</span><div class="text-muted-2">{{ $r->reason }}@if ($r->failure_reason) · <span class="text-danger">{{ $r->failure_reason }}</span>@endif</div></span>
                            <span class="text-end"><x-money :amount="$r->amount" :currency="$r->currency" /><div><x-status-badge :status="$r->status" /></div></span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @can('payments.refund')
                @if ($payment->status === \App\Enums\PaymentStatus::Completed)
                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="card-body border-top" data-confirm="Request a refund from the provider? This cannot be undone.">
                        @csrf
                        @if ($payment->provider->code === \App\Models\PaymentProvider::CRYPTOMUS && $payment->mode->value === 'live')
                            <div class="mb-2"><label class="form-label small mb-1" for="raddr">Refund wallet address</label><input id="raddr" name="address" required class="form-control form-control-sm font-mono @error('address') is-invalid @enderror" value="{{ old('address') }}">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @endif
                        <div class="mb-2"><label class="form-label small mb-1" for="rreason">Reason</label><input id="rreason" name="reason" required maxlength="255" class="form-control form-control-sm @error('reason') is-invalid @enderror" value="{{ old('reason') }}">@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="form-check mb-2"><input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="rconfirm"><label class="form-check-label small" for="rconfirm">I confirm this refund was authorised</label></div>
                        <button class="btn btn-sm btn-outline-danger" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Request refund</span></button>
                    </form>
                @endif
            @endcan
        </div>

        <div class="card"><div class="card-header">Ledger entries</div>
            @php $entries = $payment->ledgerTransactions->flatMap(fn ($t) => $t->entries->map(fn ($e) => [$t, $e])); @endphp
            @if ($entries->isEmpty())
                <x-empty-state icon="journal" title="Not settled yet" text="Entries are posted when the provider confirms the payment." />
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

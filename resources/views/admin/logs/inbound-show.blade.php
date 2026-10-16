@extends('layouts.app', ['title' => 'Callback #'.$log->id, 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.webhooks.index', ['tab' => 'inbound']) }}" class="small"><i class="bi bi-arrow-left"></i> Webhook logs</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap">Callback #{{ $log->id }} <span class="badge badge-soft text-bg-{{ $log->badge() }}">{{ ucfirst($log->status) }}</span>
            @if ($log->signature_valid)<span class="badge badge-soft text-bg-success">Signature valid</span>@else<span class="badge badge-soft text-bg-danger">Signature invalid</span>@endif</h1>
        <div class="small text-muted-2">{{ $log->provider?->name }} · received {{ $log->created_at->toDayDateTimeString() }}</div></div>
    @can('payments.reconcile')
        @if ($log->signature_valid && $log->status === \App\Models\ProviderWebhookLog::FAILED)
            <form method="POST" action="{{ route('admin.webhooks.reprocess', $log) }}" data-confirm="Re-verify the signature and process this callback again?">@csrf
                <button class="btn btn-outline-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-arrow-clockwise"></i> Reprocess</span></button></form>
        @endif
    @endcan
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header">Details</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-5 text-muted-2 fw-normal">Event type</dt><dd class="col-sm-7 font-mono">{{ $log->event_type ?? '—' }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">External ID</dt><dd class="col-sm-7 font-mono text-break">{{ $log->external_id ?? '—' }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Source IP</dt><dd class="col-sm-7 font-mono">{{ $log->ip_address ?? '—' }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Payload hash</dt><dd class="col-sm-7 font-mono text-break small">{{ $log->payload_hash ?? '—' }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Attempts</dt><dd class="col-sm-7">{{ $log->attempts }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Processed</dt><dd class="col-sm-7">{{ $log->processed_at?->toDayDateTimeString() ?? '—' }}</dd>
                @if ($log->error)<dt class="col-sm-5 text-muted-2 fw-normal">Error</dt><dd class="col-sm-7 text-danger text-break">{{ $log->error }}</dd>@endif
            </dl>
        </div></div>
        <div class="card"><div class="card-header">Headers</div><div class="card-body">
            @if ($log->headers)
                <pre class="bg-body-tertiary rounded p-2 small mb-0">{{ json_encode($log->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            @else
                <div class="small text-muted-2">No headers stored.</div>
            @endif
        </div></div>
    </div>
    <div class="col-xl-7">
        <div class="card"><div class="card-header">Raw body</div><div class="card-body">
            @php $decoded = json_decode((string) $log->raw_body, true); @endphp
            <pre class="bg-body-tertiary rounded p-2 small mb-0" style="max-height: 70vh; overflow: auto">{{ $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $log->raw_body }}</pre>
        </div></div>
    </div>
</div>
@endsection

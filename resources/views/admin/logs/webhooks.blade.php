@extends('layouts.app', ['title' => 'Webhook logs', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Webhook logs</h1><div class="small text-muted-2">Inbound provider callbacks and outbound deliveries to merchant endpoints</div></div>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item"><a class="nav-link @if ($tab === 'inbound') active @endif" href="{{ route('admin.webhooks.index', ['tab' => 'inbound']) }}">Inbound (providers)</a></li>
            <li class="nav-item"><a class="nav-link @if ($tab === 'outbound') active @endif" href="{{ route('admin.webhooks.index', ['tab' => 'outbound']) }}">Outbound (merchants)</a></li>
        </ul>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @php $statuses = $tab === 'inbound'
                ? ['received', 'processed', 'ignored', 'failed', 'rejected', 'duplicate']
                : [\App\Models\WebhookDelivery::PENDING, \App\Models\WebhookDelivery::RETRYING, \App\Models\WebhookDelivery::SUCCEEDED, \App\Models\WebhookDelivery::FAILED]; @endphp
            <select name="status" class="form-select form-select-sm" aria-label="Status"><option value="">All statuses</option>@foreach ($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>

    <div class="card">
        @if ($tab === 'inbound')
            @if ($inbound->isEmpty())
                <x-empty-state icon="inbox" title="No provider callbacks" />
            @else
                <div class="table-responsive"><table class="table table-hover table-sm">
                    <thead><tr><th>#</th><th>Received</th><th>Provider</th><th>Event</th><th>External ID</th><th>Signature</th><th>Status</th><th>IP</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($inbound as $log)
                        <tr>
                            <td class="font-mono small">{{ $log->id }}</td>
                            <td class="small text-nowrap">{{ $log->created_at->format('M j, H:i:s') }}</td>
                            <td>{{ $log->provider?->name ?? '—' }}</td>
                            <td class="font-mono small">{{ $log->event_type ?? '—' }}</td>
                            <td class="font-mono small text-break">{{ \Illuminate\Support\Str::limit($log->external_id ?? '—', 28) }}</td>
                            <td>@if ($log->signature_valid)<span class="badge badge-soft text-bg-success">Valid</span>@else<span class="badge badge-soft text-bg-danger">Invalid</span>@endif</td>
                            <td><span class="badge badge-soft text-bg-{{ $log->badge() }}">{{ ucfirst($log->status) }}</span>@if ($log->error)<div class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error, 50) }}</div>@endif</td>
                            <td class="small font-mono">{{ $log->ip_address }}</td>
                            <td class="text-end"><a href="{{ route('admin.webhooks.inbound', $log) }}" class="btn btn-sm btn-outline-primary py-0">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body border-top">{{ $inbound->links() }}</div>
            @endif
        @else
            @if ($outbound->isEmpty())
                <x-empty-state icon="send" title="No deliveries to merchants" />
            @else
                <div class="table-responsive"><table class="table table-hover table-sm">
                    <thead><tr><th>Created</th><th>Merchant</th><th>Event</th><th>Endpoint</th><th>Status</th><th class="text-end">Attempts</th><th>Last response</th><th>Next attempt</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($outbound as $d)
                        <tr>
                            <td class="small text-nowrap">{{ $d->created_at->format('M j, H:i:s') }}</td>
                            <td class="small">{{ $d->event?->merchant?->name ?? '—' }}</td>
                            <td class="font-mono small">{{ $d->event?->type }}<div class="text-muted-2">{{ $d->event?->public_id }}</div></td>
                            <td class="small text-break">{{ \Illuminate\Support\Str::limit($d->endpoint?->url ?? '—', 40) }}</td>
                            <td><span class="badge badge-soft text-bg-{{ $d->badge() }}">{{ ucfirst($d->status) }}</span></td>
                            <td class="text-end">{{ $d->attempts }}</td>
                            <td class="small">{{ $d->response_status ?? '—' }}@if ($d->last_error)<div class="text-danger">{{ \Illuminate\Support\Str::limit($d->last_error, 50) }}</div>@endif</td>
                            <td class="small text-muted-2">{{ $d->next_attempt_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-end">
                                @if (in_array($d->status, [\App\Models\WebhookDelivery::FAILED, \App\Models\WebhookDelivery::RETRYING], true))
                                    @can('webhooks.retry')<form method="POST" action="{{ route('admin.webhooks.retry', $d) }}">@csrf<button class="btn btn-sm btn-outline-secondary py-0">Retry now</button></form>@endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body border-top">{{ $outbound->links() }}</div>
            @endif
        @endif
    </div>
@endsection

@extends('layouts.app', ['title' => 'Webhooks'])

@section('header')
    <div><h1 class="page-title">Webhooks</h1><div class="small text-muted-2">Signed notifications for payment and payout status changes · {{ $dashboardMode->label() }} mode</div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEndpoint"><i class="bi bi-plus-lg"></i> Add endpoint</button>
@endsection

@section('content')
    @if ($newSecret)
        <div class="card border-success mb-4 fade-up"><div class="card-body">
            <strong><i class="bi bi-shield-lock-fill text-success"></i> Signing secret for {{ $newSecret['endpoint'] }}</strong>
            <div class="input-group mt-2"><input id="wh-secret" class="form-control font-mono" value="{{ $newSecret['secret'] }}" readonly aria-label="Signing secret"><button class="btn btn-outline-secondary" data-copy="#wh-secret" type="button"><i class="bi bi-clipboard"></i> Copy</button></div>
            <div class="small text-muted-2 mt-2">Verify every request: compute HMAC-SHA256 of <code>"{timestamp}.{raw body}"</code> with this secret and compare to <code>v1</code> in the <code>{{ $signatureHeader }}</code> header.</div>
        </div></div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Endpoints</div>
        @if ($endpoints->isEmpty())
            <x-empty-state icon="broadcast" title="No endpoints yet" text="Add an HTTPS endpoint on your server to receive events." />
        @else
            <ul class="list-group list-group-flush">
                @foreach ($endpoints as $e)
                    <li class="list-group-item bg-transparent">
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                            <div class="text-break">
                                <div class="fw-semibold font-mono">{{ $e->url }}</div>
                                <div class="small text-muted-2">{{ $e->description }} · Events: {{ $e->events ? implode(', ', $e->events) : 'all' }}</div>
                                <div class="small text-muted-2">Last success: {{ $e->last_success_at?->diffForHumans() ?? 'never' }} · Consecutive failures: {{ $e->consecutive_failures }}</div>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge badge-soft text-bg-{{ $e->is_active ? 'success' : 'secondary' }} align-self-center">{{ $e->is_active ? 'Active' : 'Disabled' }}</span>
                                <form method="POST" action="{{ route('merchant.webhooks.ping', $e) }}">@csrf<button class="btn btn-sm btn-outline-secondary" title="Send test event"><i class="bi bi-send"></i></button></form>
                                <form method="POST" action="{{ route('merchant.webhooks.update', $e) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $e->is_active ? 0 : 1 }}"><button class="btn btn-sm btn-outline-secondary" title="{{ $e->is_active ? 'Disable' : 'Enable' }}"><i class="bi bi-{{ $e->is_active ? 'pause' : 'play' }}"></i></button></form>
                                <form method="POST" action="{{ route('merchant.webhooks.roll-secret', $e) }}" data-confirm="Roll the signing secret? The old secret stops working immediately.">@csrf<button class="btn btn-sm btn-outline-secondary" title="Roll secret"><i class="bi bi-arrow-repeat"></i></button></form>
                                <form method="POST" action="{{ route('merchant.webhooks.destroy', $e) }}" data-confirm="Remove this endpoint?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button></form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card">
        <div class="card-header">Recent deliveries</div>
        @if ($deliveries->isEmpty())
            <x-empty-state icon="inbox" title="No deliveries yet" />
        @else
            <div class="table-responsive"><table class="table table-sm table-hover">
                <thead><tr><th>Event</th><th>Event ID</th><th>Endpoint</th><th>Status</th><th>Attempts</th><th>Response</th><th>Next retry</th><th></th></tr></thead>
                <tbody>
                @foreach ($deliveries as $d)
                    <tr>
                        <td class="font-mono small">{{ $d->event->type }}</td>
                        <td class="font-mono small">{{ $d->event->public_id }}</td>
                        <td class="small text-truncate" style="max-width:14rem">{{ $d->endpoint->url }}</td>
                        <td><span class="badge badge-soft text-bg-{{ $d->badge() }}">{{ ucfirst($d->status) }}</span></td>
                        <td>{{ $d->attempts }}</td>
                        <td class="small">{{ $d->response_status ?? '—' }} @if ($d->last_error)<span class="text-danger" title="{{ $d->last_error }}"><i class="bi bi-exclamation-circle"></i></span>@endif</td>
                        <td class="small">{{ $d->next_attempt_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">@if ($d->status !== 'succeeded')<form method="POST" action="{{ route('merchant.webhooks.retry', $d) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry now</button></form>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $deliveries->links() }}</div>
        @endif
    </div>

    <div class="modal fade" id="addEndpoint" tabindex="-1" aria-labelledby="addEndpointLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('merchant.webhooks.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="addEndpointLabel">Add {{ strtolower($dashboardMode->label()) }} webhook endpoint</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="wh-url">Endpoint URL</label><input id="wh-url" type="url" name="url" class="form-control font-mono" placeholder="https://example.com/webhooks/gateway" required maxlength="500">
                    <div class="form-text">@if ($dashboardMode->value === 'live') Must use HTTPS and a public address. @else Public HTTPS recommended. Private network addresses are blocked in production. @endif</div></div>
                <div class="mb-3"><label class="form-label" for="wh-desc">Description</label><input id="wh-desc" name="description" class="form-control" maxlength="255"></div>
                <div><label class="form-label d-block">Events <span class="text-muted-2 small">(leave all unchecked to receive every event)</span></label>
                    <div class="row row-cols-2 row-cols-md-3 g-1">
                        @foreach ($eventTypes as $t)
                            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="{{ $t }}" id="ev-{{ $loop->index }}"><label class="form-check-label small font-mono" for="ev-{{ $loop->index }}">{{ $t }}</label></div></div>
                        @endforeach
                    </div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add endpoint</button></div>
        </form></div>
    </div>
@endsection

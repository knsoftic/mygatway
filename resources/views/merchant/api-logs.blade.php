@extends('layouts.app', ['title' => 'API logs'])

@section('header')
    <div><h1 class="page-title">API logs</h1><div class="small text-muted-2">Request metadata for the last 90 days. Request bodies and secrets are never logged.</div></div>
@endsection

@section('content')
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <select name="status" class="form-select form-select-sm" style="max-width:12rem" aria-label="Result" data-autosubmit>
            <option value="">All results</option><option value="success" @selected(($filters['status'] ?? '') === 'success')>Successful</option><option value="error" @selected(($filters['status'] ?? '') === 'error')>Errors only</option>
        </select>
        <select name="key" class="form-select form-select-sm" style="max-width:16rem" aria-label="API key" data-autosubmit>
            <option value="">All keys</option>@foreach ($keys as $k)<option value="{{ $k->id }}" @selected((int) ($filters['key'] ?? 0) === $k->id)>{{ $k->name }} ({{ $k->key_id }})</option>@endforeach
        </select>
    </form>
    <div class="card">
        @if ($logs->isEmpty())
            <x-empty-state icon="activity" title="No API requests yet" />
        @else
            <div class="table-responsive"><table class="table table-sm table-hover">
                <thead><tr><th>Time</th><th>Request</th><th>Status</th><th>Error code</th><th>Key</th><th>IP</th><th class="text-end">Duration</th><th>Request ID</th></tr></thead>
                <tbody>
                @foreach ($logs as $l)
                    <tr>
                        <td class="small text-nowrap">{{ $l->created_at->format('M j, H:i:s') }}</td>
                        <td class="font-mono small"><span class="fw-semibold">{{ $l->method }}</span> {{ $l->path }}</td>
                        <td><span class="badge badge-soft text-bg-{{ $l->status_code >= 500 ? 'danger' : ($l->status_code >= 400 ? 'warning' : 'success') }}">{{ $l->status_code }}</span></td>
                        <td class="font-mono small">{{ $l->error_code ?? '—' }}</td>
                        <td class="small">{{ $l->apiKey?->name ?? '—' }}</td>
                        <td class="small font-mono">{{ $l->ip_address }}</td>
                        <td class="text-end small">{{ $l->duration_ms }} ms</td>
                        <td class="font-mono small text-muted-2">{{ \Illuminate\Support\Str::limit($l->request_id, 13) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection

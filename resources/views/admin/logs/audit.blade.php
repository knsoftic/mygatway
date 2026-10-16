@extends('layouts.app', ['title' => 'Audit & API logs', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Audit &amp; API logs</h1><div class="small text-muted-2">Sensitive actions by users and every authenticated API request</div></div>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item"><a class="nav-link @if ($tab === 'audit') active @endif" href="{{ route('admin.audit-logs.index', ['tab' => 'audit']) }}">Audit trail</a></li>
            <li class="nav-item"><a class="nav-link @if ($tab === 'api') active @endif" href="{{ route('admin.audit-logs.index', ['tab' => 'api']) }}">API requests</a></li>
        </ul>
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab === 'audit')
                <input name="action" value="{{ $filters['action'] ?? '' }}" class="form-control form-control-sm" placeholder="Action prefix, e.g. payout." aria-label="Action" style="max-width: 220px">
            @else
                <select name="status" class="form-select form-select-sm" aria-label="Result"><option value="">All results</option><option value="success" @selected(($filters['status'] ?? '') === 'success')>Success (&lt; 400)</option><option value="error" @selected(($filters['status'] ?? '') === 'error')>Errors (≥ 400)</option></select>
            @endif
            <input name="merchant" type="number" min="1" value="{{ $filters['merchant'] ?? '' }}" class="form-control form-control-sm" placeholder="Merchant ID" aria-label="Merchant internal ID" style="max-width: 140px">
            <button class="btn btn-sm btn-primary">Filter</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.audit-logs.index', ['tab' => $tab]) }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a>
        </form>
    </div>

    <div class="card">
        @if ($tab === 'audit')
            @if ($audit->isEmpty())
                <x-empty-state icon="clipboard-data" title="No audit entries" />
            @else
                <div class="table-responsive"><table class="table table-sm table-hover">
                    <thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Merchant</th><th>Subject</th><th>Changes</th><th>IP</th></tr></thead>
                    <tbody>
                    @foreach ($audit as $a)
                        <tr>
                            <td class="small text-nowrap">{{ $a->created_at->format('M j, H:i:s') }}</td>
                            <td class="font-mono small">{{ $a->action }}</td>
                            <td class="small">{{ $a->user?->name ?? ucfirst($a->actor_type ?? 'system') }}</td>
                            <td class="small">{{ $a->merchant?->name ?? '—' }}</td>
                            <td class="small">{{ $a->auditable_type ? class_basename($a->auditable_type).' #'.$a->auditable_id : '—' }}</td>
                            <td class="small">
                                @if ($a->old_values || $a->new_values || $a->context)
                                    <details><summary class="text-muted-2">View</summary>
                                        <pre class="bg-body-tertiary rounded p-2 small mb-0 mt-1">{{ json_encode(array_filter(['old' => $a->old_values, 'new' => $a->new_values, 'context' => $a->context]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
                                @else — @endif
                            </td>
                            <td class="small font-mono">{{ $a->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body border-top">{{ $audit->links() }}</div>
            @endif
        @else
            @if ($api->isEmpty())
                <x-empty-state icon="code-slash" title="No API requests logged" />
            @else
                <div class="table-responsive"><table class="table table-sm table-hover">
                    <thead><tr><th>When</th><th>Merchant</th><th>Key</th><th>Mode</th><th>Request</th><th>Status</th><th class="text-end">Duration</th><th>IP</th><th>Request ID</th></tr></thead>
                    <tbody>
                    @foreach ($api as $r)
                        <tr>
                            <td class="small text-nowrap">{{ $r->created_at->format('M j, H:i:s') }}</td>
                            <td class="small">{{ $r->merchant?->name ?? '—' }}</td>
                            <td class="font-mono small">{{ $r->apiKey?->key_id ?? '—' }}</td>
                            <td class="small">{{ ucfirst((string) $r->mode) }}</td>
                            <td class="font-mono small"><span class="fw-semibold">{{ $r->method }}</span> {{ $r->path }}</td>
                            <td><span class="badge badge-soft text-bg-{{ $r->status_code >= 500 ? 'danger' : ($r->status_code >= 400 ? 'warning' : 'success') }}">{{ $r->status_code }}</span>@if ($r->error_code)<div class="small text-muted-2 font-mono">{{ $r->error_code }}</div>@endif</td>
                            <td class="text-end small">{{ $r->duration_ms !== null ? $r->duration_ms.' ms' : '—' }}</td>
                            <td class="small font-mono">{{ $r->ip_address }}</td>
                            <td class="small font-mono text-muted-2">{{ \Illuminate\Support\Str::limit($r->request_id, 13) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body border-top">{{ $api->links() }}</div>
            @endif
        @endif
    </div>
@endsection

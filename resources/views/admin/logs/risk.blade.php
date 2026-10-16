@extends('layouts.app', ['title' => 'Risk events', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Risk events</h1><div class="small text-muted-2">Suspicious activity detected by the platform: failed logins, unusual volumes, new payout destinations and similar signals</div></div>
@endsection

@section('content')
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link @if ($open) active @endif" href="{{ route('admin.risk.index', ['state' => 'open']) }}">Open</a></li>
        <li class="nav-item"><a class="nav-link @if (! $open) active @endif" href="{{ route('admin.risk.index', ['state' => 'resolved']) }}">Resolved</a></li>
    </ul>

    <div class="card">
        @if ($events->isEmpty())
            <x-empty-state icon="shield-check" title="{{ $open ? 'No open risk events' : 'No resolved risk events' }}" />
        @else
            <ul class="list-group list-group-flush">
                @foreach ($events as $e)
                    <li class="list-group-item bg-transparent">
                        <div class="d-flex justify-content-between flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <div><span class="badge badge-soft text-bg-{{ $e->badge() }}">{{ ucfirst($e->severity) }}</span> <span class="font-mono small fw-semibold">{{ $e->type }}</span>
                                    <span class="small text-muted-2">· {{ $e->created_at->format('M j, Y H:i') }} ({{ $e->created_at->diffForHumans() }})</span></div>
                                <div class="small mt-1">
                                    @if ($e->merchant)Merchant: @can('merchants.view')<a href="{{ route('admin.merchants.show', $e->merchant) }}">{{ $e->merchant->name }}</a>@else{{ $e->merchant->name }}@endcan @endif
                                    @if ($e->user) · User: {{ $e->user->email }}@endif
                                    @if ($e->subject_type) · {{ class_basename($e->subject_type) }} #{{ $e->subject_id }}@endif
                                </div>
                                @if ($e->details)
                                    <details class="small mt-1"><summary class="text-muted-2">Details</summary><pre class="bg-body-tertiary rounded p-2 small mb-0 mt-1">{{ json_encode($e->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
                                @endif
                                @if ($e->resolved_at)<div class="small text-success mt-1"><i class="bi bi-check2"></i> Resolved {{ $e->resolved_at->diffForHumans() }}: {{ $e->resolution_note }}</div>@endif
                            </div>
                            @if (! $e->resolved_at)
                                <form method="POST" action="{{ route('admin.risk.resolve', $e) }}" class="d-flex gap-2 align-items-start" style="min-width: 300px">
                                    @csrf
                                    <input name="note" required maxlength="1000" class="form-control form-control-sm" placeholder="What did you check?" aria-label="Resolution note">
                                    <button class="btn btn-sm btn-outline-success text-nowrap">Resolve</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="card-body border-top">{{ $events->links() }}</div>
        @endif
    </div>
@endsection

@extends('layouts.app', ['title' => 'Reconciliation', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Reconciliation</h1><div class="small text-muted-2">Compare the ledger and local records with provider data. Mismatches are flagged for review, never auto-corrected.</div></div>
@endsection

@section('content')
    @if ($openItems > 0)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> {{ number_format($openItems) }} open reconciliation item(s) need attention.</div>
    @endif
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">Runs</div>
            @if ($runs->isEmpty())
                <x-empty-state icon="arrow-repeat" title="No reconciliation runs yet" text="Scheduled runs happen nightly; you can also start one manually." />
            @else
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>#</th><th>Type</th><th>Provider</th><th>Status</th><th class="text-end">Checked</th><th class="text-end">Issues</th><th>Triggered by</th><th>Started</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($runs as $r)
                        <tr>
                            <td class="font-mono small">{{ $r->id }}</td>
                            <td>{{ ucfirst($r->type) }}</td>
                            <td>{{ $r->provider?->name ?? '—' }}</td>
                            <td><span class="badge badge-soft text-bg-{{ match ($r->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'info', default => 'secondary' } }}">{{ ucfirst($r->status) }}</span></td>
                            <td class="text-end">{{ number_format($r->checked_count) }}</td>
                            <td class="text-end {{ $r->mismatch_count > 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($r->mismatch_count) }}</td>
                            <td class="small">{{ $r->triggeredBy?->name ?? 'Scheduler' }}</td>
                            <td class="small text-muted-2 text-nowrap">{{ ($r->started_at ?? $r->created_at)->format('M j, H:i') }}@if ($r->finished_at)<div>{{ $r->started_at ? $r->started_at->diffForHumans($r->finished_at, true) : '' }}</div>@endif</td>
                            <td class="text-end"><a href="{{ route('admin.reconciliation.show', $r) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body border-top">{{ $runs->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-4"><div class="card-header">Ledger check</div><div class="card-body small">
            <p class="text-muted-2">Verifies every ledger transaction balances (debits = credits) and that cached account balances match their entries.</p>
            <form method="POST" action="{{ route('admin.reconciliation.run') }}">@csrf<input type="hidden" name="type" value="ledger">
                <button class="btn btn-sm btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Run ledger check</span></button></form>
        </div></div>
        <div class="card"><div class="card-header">Provider check</div>
            <form method="POST" action="{{ route('admin.reconciliation.run') }}" class="card-body row g-2">
                @csrf <input type="hidden" name="type" value="provider">
                <p class="small text-muted-2 mb-1">Queries the provider for recent live transactions and compares statuses and amounts.</p>
                <div class="col-7"><label class="form-label small mb-1" for="prov">Provider</label><select id="prov" name="provider_id" class="form-select form-select-sm">@foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                <div class="col-5"><label class="form-label small mb-1" for="days">Days back</label><input id="days" name="days" type="number" min="1" max="90" value="2" class="form-control form-control-sm"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Run provider check</span></button></div>
            </form>
        </div>
    </div>
</div>
@endsection

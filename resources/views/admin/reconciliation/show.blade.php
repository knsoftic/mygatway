@extends('layouts.app', ['title' => 'Reconciliation run #'.$run->id, 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.reconciliation.index') }}" class="small"><i class="bi bi-arrow-left"></i> Reconciliation</a>
        <h1 class="page-title mt-1">Run #{{ $run->id }} · {{ ucfirst($run->type) }}@if ($run->provider) · {{ $run->provider->name }}@endif</h1>
        <div class="small text-muted-2">{{ ucfirst($run->status) }} · {{ number_format($run->checked_count) }} checked · {{ number_format($run->mismatch_count) }} issue(s) · {{ ($run->started_at ?? $run->created_at)->toDayDateTimeString() }}</div></div>
@endsection

@section('content')
    @if ($run->summary)
        <div class="card mb-4"><div class="card-header">Summary</div><div class="card-body">
            <dl class="row small mb-0">
                @foreach ($run->summary as $key => $value)
                    <dt class="col-sm-4 text-muted-2 fw-normal">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd class="col-sm-8">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                @endforeach
            </dl>
        </div></div>
    @endif

    <div class="card">
        <div class="card-header">Issues</div>
        @if ($items->isEmpty())
            <x-empty-state icon="check2-circle" title="No issues found" text="Everything checked in this run matched." />
        @else
            <ul class="list-group list-group-flush">
                @foreach ($items as $item)
                    <li class="list-group-item bg-transparent">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $item->issue)) }}</span>
                                <span class="badge badge-soft text-bg-{{ $item->status === 'open' ? 'warning' : 'success' }} ms-1">{{ ucfirst($item->status) }}</span>
                                <div class="small">
                                    @if ($item->subject instanceof \App\Models\Payment)
                                        Payment <a class="font-mono" href="{{ route('admin.payments.show', $item->subject) }}">{{ $item->subject->public_id }}</a> · <x-status-badge :status="$item->subject->status" />
                                    @elseif ($item->subject instanceof \App\Models\Payout)
                                        Payout <a class="font-mono" href="{{ route('admin.payouts.show', $item->subject) }}">{{ $item->subject->public_id }}</a> · <x-status-badge :status="$item->subject->status" />
                                    @elseif ($item->subject)
                                        {{ class_basename($item->subject) }} #{{ $item->subject->getKey() }}
                                    @endif
                                </div>
                                @if ($item->details)
                                    <pre class="bg-body-tertiary rounded p-2 small mt-2 mb-0">{{ json_encode($item->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                                @if ($item->resolved_at)<div class="small text-muted-2 mt-1">Resolved {{ $item->resolved_at->diffForHumans() }}</div>@endif
                            </div>
                            @if ($item->status === 'open')
                                <form method="POST" action="{{ route('admin.reconciliation.resolve', $item) }}" class="d-flex flex-column gap-2" style="min-width: 260px">
                                    @csrf
                                    <input name="note" required maxlength="1000" class="form-control form-control-sm" placeholder="Resolution note" aria-label="Resolution note">
                                    @if ($item->subject instanceof \App\Models\Payment || $item->subject instanceof \App\Models\Payout)
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="reverify" value="1" id="rv{{ $item->id }}" checked><label class="form-check-label small" for="rv{{ $item->id }}">Re-verify with provider first</label></div>
                                    @endif
                                    <button class="btn btn-sm btn-outline-success">Resolve</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="card-body border-top">{{ $items->links() }}</div>
        @endif
    </div>
@endsection

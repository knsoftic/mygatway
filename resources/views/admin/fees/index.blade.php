@extends('layouts.app', ['title' => 'Fee rules', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Fee rules</h1><div class="small text-muted-2">The most specific active rule wins: merchant &gt; currency &gt; provider. Every transaction keeps a frozen copy of the fee it was charged.</div></div>
    @can('fees.manage')
        <a class="btn btn-primary" href="{{ route('admin.fee-rules.create') }}"><i class="bi bi-plus-lg"></i> New rule</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="dir">Direction</label>
            <select id="dir" name="direction" class="form-select form-select-sm"><option value="">All</option>@foreach (\App\Enums\FeeDirection::cases() as $d)<option value="{{ $d->value }}" @selected(($filters['direction'] ?? '') === $d->value)>{{ $d->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="scope">Scope</label>
            <select id="scope" name="scope" class="form-select form-select-sm"><option value="">All</option><option value="global" @selected(($filters['scope'] ?? '') === 'global')>Global</option><option value="merchant" @selected(($filters['scope'] ?? '') === 'merchant')>Merchant-specific</option></select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="active">State</label>
            <select id="active" name="active" class="form-select form-select-sm"><option value="">All</option><option value="1" @selected(($filters['active'] ?? null) === '1' || ($filters['active'] ?? null) === true)>Active</option><option value="0" @selected(($filters['active'] ?? null) === '0' || ($filters['active'] ?? null) === false)>Inactive</option></select></div>
        <div class="col-6 col-md-3 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.fee-rules.index') }}" aria-label="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div></form>

    <div class="card mb-4">
        @if ($rules->isEmpty())
            <x-empty-state icon="percent" title="No fee rules" text="Without a matching rule, transactions are charged no service fee." />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Rule</th><th>Direction</th><th>Merchant</th><th>Provider</th><th>Currency</th><th>Fee</th><th>Min / Max</th><th>Effective</th><th>State</th><th></th></tr></thead>
                <tbody>
                @foreach ($rules as $r)
                    @php $inWindow = $r->effective_from->isPast() && (! $r->effective_until || $r->effective_until->isFuture()); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $r->name }}<div class="small text-muted-2">#{{ $r->id }} · specificity {{ $r->specificity() }}</div></td>
                        <td>{{ $r->direction->label() }}</td>
                        <td class="small">@if ($r->merchant)@can('merchants.view')<a href="{{ route('admin.merchants.show', $r->merchant) }}">{{ $r->merchant->name }}</a>@else{{ $r->merchant->name }}@endcan @else<span class="text-muted-2">All merchants</span>@endif</td>
                        <td class="small">{{ $r->provider?->name ?? 'Any' }}</td>
                        <td class="small">{{ $r->currency ?? 'Any' }}</td>
                        <td class="fw-semibold">{{ $r->describe() }}</td>
                        <td class="small">{{ $r->min_fee ? \App\Support\Decimal::format($r->min_fee) : '—' }} / {{ $r->max_fee ? \App\Support\Decimal::format($r->max_fee) : '—' }}</td>
                        <td class="small text-nowrap">{{ $r->effective_from->format('M j, Y') }}<div class="text-muted-2">{{ $r->effective_until ? 'until '.$r->effective_until->format('M j, Y') : 'no end date' }}</div></td>
                        <td>
                            @if (! $r->is_active)<span class="badge badge-soft text-bg-secondary">Inactive</span>
                            @elseif (! $inWindow)<span class="badge badge-soft text-bg-warning">{{ $r->effective_from->isFuture() ? 'Scheduled' : 'Ended' }}</span>
                            @else<span class="badge badge-soft text-bg-success">Active</span>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('fees.manage')
                                <a href="{{ route('admin.fee-rules.edit', $r) }}" class="btn btn-sm btn-outline-secondary" aria-label="Edit {{ $r->name }}"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.fee-rules.toggle', $r) }}" class="d-inline" data-confirm="{{ $r->is_active ? 'Deactivate' : 'Activate' }} this rule?">@csrf
                                    <button class="btn btn-sm btn-outline-{{ $r->is_active ? 'danger' : 'success' }}" aria-label="{{ $r->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi bi-{{ $r->is_active ? 'pause' : 'play' }}"></i></button></form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $rules->links() }}</div>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card"><div class="card-header">Preview a fee</div>
                <form method="GET" action="{{ route('admin.fee-rules.preview') }}" class="card-body row g-2">
                    <div class="col-6"><label class="form-label small mb-1" for="pdir">Direction</label><select id="pdir" name="direction" class="form-select form-select-sm">@foreach (\App\Enums\FeeDirection::cases() as $d)<option value="{{ $d->value }}">{{ $d->label() }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-1" for="pmid">Merchant ID</label><input id="pmid" name="merchant_id" type="number" min="1" required class="form-control form-control-sm" placeholder="Internal ID"></div>
                    <div class="col-6"><label class="form-label small mb-1" for="ppid">Provider</label><select id="ppid" name="provider_id" class="form-select form-select-sm">@foreach (\App\Models\PaymentProvider::query()->orderBy('priority')->get() as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-1" for="pcur">Currency</label><input id="pcur" name="currency" required maxlength="16" class="form-control form-control-sm text-uppercase" placeholder="USDT"></div>
                    <div class="col-12"><label class="form-label small mb-1" for="pamt">Amount</label><input id="pamt" name="amount" required inputmode="decimal" class="form-control form-control-sm" placeholder="10000"></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary">Calculate</button></div>
                </form>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card"><div class="card-header">Recent changes</div>
                @if ($history->isEmpty())
                    <div class="card-body small text-muted-2">No changes recorded yet.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($history as $h)
                            <li class="list-group-item bg-transparent small d-flex justify-content-between">
                                <span><span class="font-mono">{{ $h->action }}</span> · rule #{{ $h->auditable_id }}
                                    @php $changed = collect($h->new_values ?? [])->filter(fn ($v, $k) => ($h->old_values[$k] ?? null) !== $v && ! in_array($k, ['id'], true))->keys(); @endphp
                                    @if ($h->old_values && $changed->isNotEmpty())<div class="text-muted-2">Changed: {{ $changed->implode(', ') }}</div>@endif</span>
                                <span class="text-muted-2 text-end">{{ $h->user?->name ?? 'System' }}<div>{{ $h->created_at->format('M j, H:i') }}</div></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

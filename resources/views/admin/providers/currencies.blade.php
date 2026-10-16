@extends('layouts.app', ['title' => $provider->name.' currencies', 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.providers.index') }}" class="small"><i class="bi bi-arrow-left"></i> Providers</a><h1 class="page-title mt-1">{{ $provider->name }} currencies</h1>
        <div class="small text-muted-2">Currencies synced from the provider are marked "sync". Only enabled and available rows are offered to merchants.</div></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            @if ($currencies->isEmpty())
                <x-empty-state icon="currency-bitcoin" title="No currencies yet" text="Run “Sync currencies” on the providers page or add one manually." />
            @else
                <div class="table-responsive"><table class="table table-hover table-sm">
                    <thead><tr><th>Direction</th><th>Currency</th><th>Network</th><th class="text-end">Min</th><th class="text-end">Max</th><th class="text-end">Provider fee</th><th>Source</th><th>State</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($currencies as $c)
                        <tr class="{{ $c->is_enabled && $c->is_available ? '' : 'text-muted-2' }}">
                            <td>{{ $c->direction === 'payin' ? 'Pay In' : 'Pay Out' }}</td>
                            <td class="fw-semibold">{{ $c->currency }}</td>
                            <td>{{ $c->networkLabel() }}</td>
                            <td class="text-end">{{ $c->min_amount !== null ? \App\Support\Decimal::format($c->min_amount) : '—' }}</td>
                            <td class="text-end">{{ $c->max_amount !== null ? \App\Support\Decimal::format($c->max_amount) : '—' }}</td>
                            <td class="text-end small">@if ($c->provider_fee_percent !== null || $c->provider_fee_fixed !== null){{ rtrim(rtrim((string) $c->provider_fee_percent, '0'), '.') ?: '0' }}% + {{ \App\Support\Decimal::format($c->provider_fee_fixed ?? '0') }}@else — @endif</td>
                            <td class="small">{{ $c->source }}@if ($c->synced_at)<div class="text-muted-2">{{ $c->synced_at->diffForHumans() }}</div>@endif</td>
                            <td>
                                @if (! $c->is_available)<span class="badge badge-soft text-bg-warning">Unavailable</span>
                                @elseif ($c->is_enabled)<span class="badge badge-soft text-bg-success">Enabled</span>
                                @else<span class="badge badge-soft text-bg-secondary">Disabled</span>@endif
                            </td>
                            <td class="text-end"><form method="POST" action="{{ route('admin.providers.currencies.toggle', $c) }}">@csrf<button class="btn btn-sm btn-outline-{{ $c->is_enabled ? 'danger' : 'success' }} py-0">{{ $c->is_enabled ? 'Disable' : 'Enable' }}</button></form></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card"><div class="card-header">Add or update a currency</div>
            <form method="POST" action="{{ route('admin.providers.currencies.store', $provider) }}" class="card-body row g-2">
                @csrf
                <div class="col-6"><label class="form-label small mb-1" for="dir">Direction</label><select id="dir" name="direction" class="form-select form-select-sm"><option value="payin">Pay In</option><option value="payout">Pay Out</option></select></div>
                <div class="col-6"><label class="form-label small mb-1" for="cur">Currency</label><input id="cur" name="currency" required maxlength="16" value="{{ old('currency') }}" class="form-control form-control-sm text-uppercase @error('currency') is-invalid @enderror" placeholder="USDT">@error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label small mb-1" for="net">Network <span class="text-muted-2">(optional)</span></label><input id="net" name="network" maxlength="32" value="{{ old('network') }}" class="form-control form-control-sm @error('network') is-invalid @enderror" placeholder="tron">@error('network')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-6"><label class="form-label small mb-1" for="min">Min amount</label><input id="min" name="min_amount" inputmode="decimal" value="{{ old('min_amount') }}" class="form-control form-control-sm @error('min_amount') is-invalid @enderror"></div>
                <div class="col-6"><label class="form-label small mb-1" for="max">Max amount</label><input id="max" name="max_amount" inputmode="decimal" value="{{ old('max_amount') }}" class="form-control form-control-sm @error('max_amount') is-invalid @enderror"></div>
                <div class="col-12"><div class="form-text mb-2">Only add currencies the provider actually supports for your account. An existing row with the same direction, currency and network is updated.</div><button class="btn btn-sm btn-primary">Save currency</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

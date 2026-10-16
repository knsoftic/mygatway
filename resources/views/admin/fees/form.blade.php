@extends('layouts.app', ['title' => $rule->exists ? 'Edit fee rule' : 'New fee rule', 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.fee-rules.index') }}" class="small"><i class="bi bi-arrow-left"></i> Fee rules</a><h1 class="page-title mt-1">{{ $rule->exists ? 'Edit fee rule #'.$rule->id : 'New fee rule' }}</h1></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        @if ($rule->exists)
            <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Changes apply to new transactions only. Existing transactions keep the fee they were charged. To schedule a price change, create a new rule with a later effective date instead of editing this one.</div>
        @endif
        <form method="POST" action="{{ $rule->exists ? route('admin.fee-rules.update', $rule) : route('admin.fee-rules.store') }}" class="card" novalidate>
            @csrf
            @if ($rule->exists) @method('PUT') @endif
            <div class="card-body row g-3">
                <div class="col-md-8"><label class="form-label" for="name">Rule name</label>
                    <input id="name" name="name" value="{{ old('name', $rule->name) }}" required maxlength="255" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Standard Pay In">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="direction">Direction</label>
                    <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror">@foreach (\App\Enums\FeeDirection::cases() as $d)<option value="{{ $d->value }}" @selected(old('direction', $rule->direction?->value ?? $rule->direction) === $d->value)>{{ $d->label() }}</option>@endforeach</select>@error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Applies to</div></div>
                <div class="col-md-4"><label class="form-label" for="merchant_id">Merchant</label>
                    <select id="merchant_id" name="merchant_id" class="form-select @error('merchant_id') is-invalid @enderror"><option value="">All merchants</option>@foreach ($merchants as $m)<option value="{{ $m->id }}" @selected((string) old('merchant_id', $rule->merchant_id) === (string) $m->id)>{{ $m->name }} ({{ $m->public_id }})</option>@endforeach</select>@error('merchant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="provider_id">Provider</label>
                    <select id="provider_id" name="provider_id" class="form-select @error('provider_id') is-invalid @enderror"><option value="">Any provider</option>@foreach ($providers as $p)<option value="{{ $p->id }}" @selected((string) old('provider_id', $rule->provider_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach</select>@error('provider_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="currency">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', $rule->currency) }}" maxlength="16" class="form-control text-uppercase @error('currency') is-invalid @enderror" placeholder="Any">@error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Required for fixed, min or max fees.</div></div>

                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Fee</div></div>
                <div class="col-md-3"><label class="form-label" for="percentage">Percentage</label>
                    <div class="input-group"><input id="percentage" name="percentage" inputmode="decimal" value="{{ old('percentage', $rule->percentage !== null ? rtrim(rtrim((string) $rule->percentage, '0'), '.') : '0') }}" required class="form-control @error('percentage') is-invalid @enderror"><span class="input-group-text">%</span>@error('percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                <div class="col-md-3"><label class="form-label" for="fixed_amount">Fixed amount</label>
                    <input id="fixed_amount" name="fixed_amount" inputmode="decimal" value="{{ old('fixed_amount', $rule->fixed_amount !== null ? \App\Support\Decimal::format($rule->fixed_amount, 8) : '0') }}" class="form-control @error('fixed_amount') is-invalid @enderror">@error('fixed_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="min_fee">Minimum fee</label>
                    <input id="min_fee" name="min_fee" inputmode="decimal" value="{{ old('min_fee', $rule->min_fee) }}" class="form-control @error('min_fee') is-invalid @enderror" placeholder="None">@error('min_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="max_fee">Maximum fee</label>
                    <input id="max_fee" name="max_fee" inputmode="decimal" value="{{ old('max_fee', $rule->max_fee) }}" class="form-control @error('max_fee') is-invalid @enderror" placeholder="None">@error('max_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Schedule</div></div>
                <div class="col-md-4"><label class="form-label" for="effective_from">Effective from</label>
                    <input id="effective_from" type="datetime-local" name="effective_from" value="{{ old('effective_from', $rule->effective_from?->format('Y-m-d\TH:i')) }}" required class="form-control @error('effective_from') is-invalid @enderror">@error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="effective_until">Effective until <span class="text-muted-2">(optional)</span></label>
                    <input id="effective_until" type="datetime-local" name="effective_until" value="{{ old('effective_until', $rule->effective_until?->format('Y-m-d\TH:i')) }}" class="form-control @error('effective_until') is-invalid @enderror">@error('effective_until')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $rule->is_active))><label class="form-check-label" for="is_active">Active</label></div></div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                <a href="{{ route('admin.fee-rules.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">{{ $rule->exists ? 'Save changes' : 'Create rule' }}</span></button>
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card"><div class="card-header">How fees are calculated</div><div class="card-body small">
            <p class="mb-2">fee = amount × percentage + fixed amount, then clamped to the minimum and maximum.</p>
            <p class="mb-2"><strong>Pay In:</strong> the fee is deducted from what the customer paid. On 10,000 at 1%, the fee is 100 and the merchant receives 9,900.</p>
            <p class="mb-2"><strong>Pay Out:</strong> the fee is added on top. On 10,000 at 1%, the merchant's balance is debited 10,100 plus any network fee.</p>
            <p class="mb-0 text-muted-2">When several rules match, the most specific one is used: merchant &gt; currency &gt; provider.</p>
        </div></div>
    </div>
</div>
@endsection

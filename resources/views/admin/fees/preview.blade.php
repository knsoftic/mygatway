@extends('layouts.app', ['title' => 'Fee preview', 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.fee-rules.index') }}" class="small"><i class="bi bi-arrow-left"></i> Fee rules</a><h1 class="page-title mt-1">Fee preview</h1>
        <div class="small text-muted-2">{{ $input['direction'] === 'payin' ? 'Pay In' : 'Pay Out' }} · {{ $merchant?->name }} · {{ $provider?->name }} · {{ strtoupper($input['currency']) }}</div></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card"><div class="card-header">Result</div><div class="card-body">
            @php
                $isPayIn = $input['direction'] === 'payin';
                $result = $isPayIn ? \App\Support\Decimal::sub($quote->baseAmount, $quote->amount) : \App\Support\Decimal::add($quote->baseAmount, $quote->amount);
            @endphp
            <table class="table table-sm mb-0">
                <tr><td>{{ $isPayIn ? 'Gross amount' : 'Requested amount' }}</td><td class="text-end"><x-money :amount="$quote->baseAmount" :currency="$quote->currency" /></td></tr>
                <tr><td>Service fee</td><td class="text-end">{{ $isPayIn ? '−' : '+' }} <x-money :amount="$quote->amount" :currency="$quote->currency" /></td></tr>
                <tr class="fw-bold"><td>{{ $isPayIn ? 'Merchant net' : 'Total debit (before network fee)' }}</td><td class="text-end"><x-money :amount="$result" :currency="$quote->currency" /></td></tr>
            </table>
            <div class="small text-muted-2 mt-2">{{ rtrim(rtrim($quote->percentage, '0'), '.') ?: '0' }}% + {{ \App\Support\Decimal::format($quote->fixedAmount) }}@if ($quote->minFee) · min {{ $quote->minFee }}@endif @if ($quote->maxFee) · max {{ $quote->maxFee }}@endif</div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card"><div class="card-header">Matched rule</div><div class="card-body small">
            @if ($rule)
                <div class="fw-semibold">{{ $rule->name }} <span class="text-muted-2">#{{ $rule->id }}</span></div>
                <div>{{ $rule->describe() }} · {{ $rule->merchant_id ? 'merchant-specific' : 'global' }} · {{ $rule->provider?->name ?? 'any provider' }} · {{ $rule->currency ?? 'any currency' }}</div>
                @can('fees.manage')<a href="{{ route('admin.fee-rules.edit', $rule) }}" class="btn btn-sm btn-outline-secondary mt-2">Edit rule</a>@endcan
            @else
                <div class="text-warning"><i class="bi bi-exclamation-triangle"></i> No active rule matches, so no service fee would be charged.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection

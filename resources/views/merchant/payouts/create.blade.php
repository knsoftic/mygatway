@extends('layouts.app', ['title' => 'Request payout'])

@section('header')
    <div><a href="{{ route('merchant.payouts.index') }}" class="small"><i class="bi bi-arrow-left"></i> Payouts</a><h1 class="page-title mt-1">Request payout</h1></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-7">
        @if (! $canRequest)
            <div class="alert alert-warning">Live payouts require approved business verification and two-factor authentication.</div>
        @endif
        @if ($providers->isEmpty())
            <div class="card"><x-empty-state icon="plug" title="No payout provider is available" text="Payouts are not enabled on the platform yet." /></div>
        @else
        <form method="POST" action="{{ route('merchant.payouts.store') }}" class="card" novalidate>
            @csrf
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="provider">Provider</label>
                    <select id="provider" name="provider" class="form-select @error('provider') is-invalid @enderror" required data-payout-provider>
                        @foreach ($providers as $p)<option value="{{ $p['code'] }}" @selected(old('provider') === $p['code'])>{{ $p['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="destination_type">Destination type</label>
                    <select id="destination_type" name="destination_type" class="form-select @error('destination_type') is-invalid @enderror" required data-payout-destination-type></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required data-payout-currency></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="network">Network</label>
                    <select id="network" name="network" class="form-select @error('network') is-invalid @enderror" data-payout-network></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="amount">Amount</label>
                    <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" placeholder="0.00" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="destination">Destination</label>
                    <input id="destination" name="destination" value="{{ old('destination') }}" class="form-control font-mono @error('destination') is-invalid @enderror" required autocomplete="off" spellcheck="false">
                    @error('destination')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="memo">Memo / tag <span class="text-muted-2">(if required)</span></label>
                    <input id="memo" name="memo" value="{{ old('memo') }}" class="form-control" maxlength="64">
                </div>
                <div class="col-md-6"><label class="form-label" for="reference">Your reference <span class="text-muted-2">(optional)</span></label><input id="reference" name="reference" value="{{ old('reference') }}" class="form-control" maxlength="64"></div>
                <div class="col-md-6"><label class="form-label" for="description">Description <span class="text-muted-2">(optional)</span></label><input id="description" name="description" value="{{ old('description') }}" class="form-control" maxlength="255"></div>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input @error('confirm_destination') is-invalid @enderror" type="checkbox" value="1" name="confirm_destination" id="confirm"><label class="form-check-label small" for="confirm">I confirm the destination, currency and network are correct. Crypto transfers cannot be reversed.</label></div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <span class="small text-muted-2">Fees are calculated by the platform and shown on the next screen.</span>
                <button class="btn btn-primary" @disabled(! $canRequest) data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Request payout</span></button>
            </div>
        </form>
        @php $payoutConfig = ['providers' => $providers, 'old' => ['destination_type' => old('destination_type'), 'currency' => old('currency'), 'network' => old('network')]]; @endphp
        <script type="application/json" id="payout-config">@json($payoutConfig)</script>
        @endif
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-header">Available balance</div>
            @if (empty($balances))
                <x-empty-state icon="wallet2" title="No balance yet" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($balances as $cur => $b)
                        <li class="list-group-item bg-transparent d-flex justify-content-between"><span class="fw-semibold">{{ $cur }}</span><x-money :amount="$b['available']" /></li>
                    @endforeach
                </ul>
            @endif
            <div class="card-body small text-muted-2 border-top">The total debit (amount + service fee + network fee) is reserved immediately and returned if the payout is rejected or fails. @if ($mode->value === 'live') Live payouts are reviewed by our team before sending. @else In test mode payouts are auto-approved; a destination containing "fail" simulates a failure. @endif</div>
        </div>
    </div>
</div>
@endsection

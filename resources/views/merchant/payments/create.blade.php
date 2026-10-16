@extends('layouts.app', ['title' => 'Receive payment'])

@section('header')
    <div><a href="{{ route('merchant.payments.index') }}" class="small"><i class="bi bi-arrow-left"></i> Payments</a><h1 class="page-title mt-1">Receive payment</h1>
        <div class="small text-muted-2">Create a Pay In and send the checkout link to your customer · {{ $mode->label() }} mode</div></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-7">
        @unless ($canCreate)
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
                @if ($mode->value === 'live') Live payments are not enabled for your account yet. Complete <a href="{{ route('merchant.verification.show') }}">business verification</a>, or switch to test mode.
                @else Your account cannot create payments right now. Please contact support. @endif
            </div>
        @endunless
        @if ($currencies->isEmpty())
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No currencies are available for Pay In yet. Please try again later.</div>
        @endif

        <form method="POST" action="{{ route('merchant.payments.store') }}" class="card" novalidate>
            @csrf
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label" for="amount">Amount</label>
                    <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount') }}" required placeholder="0.00" class="form-control @error('amount') is-invalid @enderror">
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                        @foreach ($currencies as $c)<option value="{{ $c }}" @selected(old('currency', 'USDT') === $c)>{{ $c }}</option>@endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="provider">Provider</label>
                    <select id="provider" name="provider" class="form-select @error('provider') is-invalid @enderror">
                        <option value="">Automatic (best available)</option>
                        @foreach ($providers as $p)<option value="{{ $p->code }}" @selected(old('provider') === $p->code)>{{ $p->name }}</option>@endforeach
                    </select>
                    @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="lifetime">Link valid for</label>
                    <select id="lifetime" name="lifetime" class="form-select">
                        @foreach ([1800 => '30 minutes', 3600 => '1 hour', 21600 => '6 hours', 43200 => '12 hours'] as $seconds => $label)
                            <option value="{{ $seconds }}" @selected((int) old('lifetime', 3600) === $seconds)>{{ $label }}</option>
                        @endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label" for="reference">Your reference <span class="text-muted-2">(optional)</span></label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" maxlength="64" placeholder="e.g. invoice-1001" class="form-control @error('reference') is-invalid @enderror">
                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="customer_email">Customer email <span class="text-muted-2">(optional)</span></label>
                    <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" maxlength="255" class="form-control @error('customer_email') is-invalid @enderror">
                    @error('customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="description">Description <span class="text-muted-2">(optional)</span></label>
                    <input id="description" name="description" value="{{ old('description') }}" maxlength="255" placeholder="What the customer is paying for" class="form-control @error('description') is-invalid @enderror">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <span class="small text-muted-2">Fees are calculated by the platform.</span>
                <button class="btn btn-primary" data-loading @disabled(! $canCreate || $currencies->isEmpty())><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Create payment link</span></button>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-header">How it works</div><div class="card-body small">
            <ol class="ps-3 mb-0">
                <li class="mb-1">Create the payment here. You get a checkout link.</li>
                <li class="mb-1">Send the link to your customer. They pay on the provider's secure page.</li>
                <li class="mb-1">When the provider confirms the payment, the amount minus the service fee is added to <strong>your</strong> balance under <a href="{{ route('merchant.ledger.index') }}">Balance &amp; ledger</a>.</li>
                <li>Use <a href="{{ route('merchant.payouts.create') }}">Send payout</a> to withdraw from that balance.</li>
            </ol>
            @if ($mode->value === 'test')<div class="alert alert-info small mt-3 mb-0">Test mode: the link opens a sandbox page where you choose the result. No real money moves.</div>@endif
        </div></div>
    </div>
</div>
@endsection

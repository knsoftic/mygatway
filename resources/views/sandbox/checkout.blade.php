@extends('layouts.auth', ['title' => 'Test checkout'])

@section('content')
    <div class="test-banner rounded-3 px-3 py-2 mb-4 small"><i class="bi bi-cone-striped"></i> <strong>Sandbox checkout (test mode).</strong> No real payment will be made.</div>

    <div class="text-center mb-4">
        <div class="small text-muted-2">{{ $payment->merchant->name }} requests</div>
        <div class="display-6 fw-bold ls-tight"><x-money :amount="$payment->amount" :currency="$payment->currency" /></div>
        <div class="small text-muted-2">{{ $payment->description ?: 'Order '.$payment->merchant_reference }} · via {{ $payment->provider->name }} (simulated)</div>
    </div>

    <dl class="row small mb-4">
        <dt class="col-5 text-muted-2 fw-normal">Payment</dt><dd class="col-7 font-mono text-break">{{ $payment->public_id }}</dd>
        <dt class="col-5 text-muted-2 fw-normal">Status</dt><dd class="col-7"><x-status-badge :status="$payment->status" /></dd>
        @if ($payment->pay_address)<dt class="col-5 text-muted-2 fw-normal">Pay address</dt><dd class="col-7 font-mono text-break">{{ $payment->pay_address }}</dd>@endif
        <dt class="col-5 text-muted-2 fw-normal">Expires</dt><dd class="col-7">{{ $payment->expires_at?->toDayDateTimeString() }}</dd>
        @if ($payment->received_amount)<dt class="col-5 text-muted-2 fw-normal">Received</dt><dd class="col-7"><x-money :amount="$payment->received_amount" :currency="$payment->currency" /></dd>@endif
    </dl>

    @if ($payment->status->isOpen())
        <form method="POST" action="{{ $actionUrl }}" class="d-grid gap-2">
            @csrf
            <button name="outcome" value="paid" class="btn btn-success"><i class="bi bi-check2-circle"></i> Simulate successful payment</button>
            <button name="outcome" value="underpaid" class="btn btn-outline-warning"><i class="bi bi-dash-circle"></i> Simulate underpayment (90%)</button>
            <button name="outcome" value="failed" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Simulate failure</button>
            <button name="outcome" value="expired" class="btn btn-outline-secondary"><i class="bi bi-hourglass-bottom"></i> Simulate expiry</button>
        </form>
    @else
        <div class="alert alert-info small mb-0">This payment is final. Webhooks for the result were queued to the merchant's test endpoints.</div>
    @endif
@endsection

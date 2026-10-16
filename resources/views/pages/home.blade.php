@extends('layouts.public', ['title' => 'Crypto payment gateway for businesses'])

@section('content')
<section class="hero py-5 py-lg-6">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 fade-up">
                <span class="badge rounded-pill bg-brand-soft text-brand mb-3 px-3 py-2"><i class="bi bi-lightning-charge"></i> One API for crypto Pay In &amp; Pay Out</span>
                <h1 class="mb-3">Accept and send <span class="gradient-text">crypto payments</span> with one integration</h1>
                <p class="lead mb-4">{{ config('app.name') }} connects your website or app to
                    @if ($providers->isNotEmpty()){{ $providers->pluck('name')->join(', ', ' and ') }}@else our payment providers @endif
                    through a single, secure REST API, with transparent fees, verified callbacks and an auditable ledger.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('contact') }}" class="btn btn-primary btn-lg px-4">Request an account</a>
                    <a href="{{ route('docs.index') }}" class="btn btn-outline-primary btn-lg px-4"><i class="bi bi-code-slash"></i> Read the docs</a>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 small text-muted-2">
                    <span><i class="bi bi-check2-circle text-success"></i> Test mode instantly</span>
                    <span><i class="bi bi-check2-circle text-success"></i> Signed webhooks</span>
                    <span><i class="bi bi-check2-circle text-success"></i> 2FA &amp; audit logs</span>
                </div>
            </div>
            <div class="col-lg-6 fade-up">
                <div class="code-window">
                    <div class="code-head"><span class="dot" style="background:#ef4444"></span><span class="dot" style="background:#f59e0b"></span><span class="dot" style="background:#10b981"></span><span class="ms-2 small text-secondary">POST /api/v1/payments</span></div>
<pre><span class="tok-cm"># Create a payment and redirect your customer to checkout_url</span>
curl {{ url('/api/v1/payments') }} \
  -H <span class="tok-str">"Authorization: Bearer sk_test_..."</span> \
  -H <span class="tok-str">"Idempotency-Key: order-1042"</span> \
  -H <span class="tok-str">"Content-Type: application/json"</span> \
  -d <span class="tok-str">'{
    "amount": "49.90",
    "currency": "USDT",
    "reference": "order-1042",
    "success_url": "https://shop.example/thanks"
  }'</span>

<span class="tok-cm"># 201 Created</span>
{ <span class="tok-key">"id"</span>: <span class="tok-str">"pay_01j..."</span>, <span class="tok-key">"status"</span>: <span class="tok-str">"pending"</span>,
  <span class="tok-key">"checkout_url"</span>: <span class="tok-str">"https://..."</span>, <span class="tok-key">"service_fee"</span>: <span class="tok-str">"0.49900000"</span> }</pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">How it works</div>
            <h2 class="section-title">Pay In and Pay Out, end to end</h2>
            <p class="text-muted-2 mx-auto" style="max-width:40rem">Your server talks to {{ config('app.name') }}; we talk to the provider. Payment status is only ever confirmed from signed provider callbacks and authenticated status checks, never from a browser redirect.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-3"><i class="bi bi-arrow-down-left-circle text-success me-2"></i>Pay In (receive)</h3>
                    @foreach ([
                        'Your server creates a payment with your API key and an Idempotency-Key.',
                        'We calculate the service fee, pick an enabled provider and create the invoice.',
                        'Your customer pays on the provider\'s hosted checkout page.',
                        'The provider\'s signed callback is verified and re-checked with the provider API.',
                        'Your balance is credited in the ledger and you receive a signed webhook.',
                    ] as $i => $step)
                        <div class="d-flex gap-3 mb-3"><span class="step-number flex-shrink-0">{{ $i + 1 }}</span><p class="mb-0 pt-1">{{ $step }}</p></div>
                    @endforeach
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-3"><i class="bi bi-arrow-up-right-circle text-primary me-2"></i>Pay Out (send)</h3>
                    @foreach ([
                        'Request a payout from the dashboard or API, to a supported destination.',
                        'We check your available balance and reserve amount + fees atomically.',
                        'An administrator reviews and approves the payout.',
                        'The payout is sent through the provider with an idempotent reference.',
                        'The final provider status settles the ledger and triggers your webhook.',
                    ] as $i => $step)
                        <div class="d-flex gap-3 mb-3"><span class="step-number flex-shrink-0">{{ $i + 1 }}</span><p class="mb-0 pt-1">{{ $step }}</p></div>
                    @endforeach
                </div></div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow mb-2">Built for trust</div>
            <h2 class="section-title">Everything a payment business needs</h2>
        </div>
        <div class="row g-4">
            @foreach ([
                ['plug', 'Unified API', 'One consistent REST API across providers. Unsupported operations return a clear error, never a fake success.'],
                ['journal-check', 'Double-entry ledger', 'Every balance change is a balanced, immutable journal. Balances are never edited directly.'],
                ['percent', 'Transparent fees', 'Fees are calculated server-side from versioned rules and frozen on every transaction.'],
                ['broadcast', 'Signed webhooks', 'HMAC-signed notifications with event IDs, automatic retries and a full delivery history.'],
                ['shield-lock', 'Security first', 'Hashed API secrets, IP allowlists, 2FA, rate limits, idempotency and audit logs.'],
                ['cone-striped', 'Test mode', 'A built-in sandbox simulator lets you integrate before going live, with zero real funds.'],
            ] as [$icon, $title, $text])
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card"><div class="card-body p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-{{ $icon }}"></i></div>
                        <h3 class="h6 fw-bold">{{ $title }}</h3>
                        <p class="small text-muted-2 mb-0">{{ $text }}</p>
                    </div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="card overflow-hidden"><div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <div class="eyebrow mb-2">Pricing</div>
                    <h2 class="section-title h3">Simple, published fees</h2>
                    <p class="text-muted-2 mb-0">Standard rates below. Volume-based and merchant-specific pricing is available.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach (['payin' => 'Pay In', 'payout' => 'Pay Out'] as $key => $label)
                            <div class="col-sm-6">
                                <div class="border rounded-3 p-4 h-100 bg-surface">
                                    <div class="small text-muted-2 fw-semibold text-uppercase">{{ $label }}</div>
                                    <div class="display-6 fw-bold ls-tight my-1">{{ $pricing[$key]?->describe() ?? 'Contact us' }}</div>
                                    <div class="small text-muted-2">per {{ $key === 'payin' ? 'successful payment' : 'completed payout' }}@if ($key === 'payout'), plus provider network fee @endif</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('pricing') }}" class="btn btn-link px-0 mt-2">See full pricing <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div></div>
    </div>
</section>

<section class="section pt-0">
    <div class="container text-center">
        <h2 class="section-title mb-3">Start integrating in minutes</h2>
        <p class="text-muted-2 mb-4">Tell us about your business. Once our team opens your account, grab a test API key and make your first payment.</p>
        <a href="{{ route('contact') }}" class="btn btn-primary btn-lg px-4">Request an account</a>
        <a href="{{ route('contact') }}" class="btn btn-link btn-lg">Talk to sales</a>
    </div>
</section>
@endsection

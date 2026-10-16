@extends('layouts.public', ['title' => 'How it works'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'How it works', 'heading' => 'From your account to your first live payment', 'intro' => 'A predictable path: integrate in test mode, verify your business, then switch to live keys.'])

<section class="section pt-4">
    <div class="container" style="max-width:56rem">
        @foreach ([
            ['person-plus', 'Request your merchant account', 'Send us your business details. Our team opens your account and emails you a link to set your password. Test mode is available immediately.'],
            ['key', 'Generate test API keys', 'Create a test secret key in the dashboard. Secrets are shown once and stored only as a hash.'],
            ['code-slash', 'Integrate the API', 'Create payments from your server, redirect customers to checkout_url, and listen for signed webhooks.'],
            ['cone-striped', 'Test every outcome', 'Use the sandbox checkout to simulate paid, underpaid, failed and expired payments, plus payouts.'],
            ['patch-check', 'Verify your business', 'Submit your business details and documents. Our team reviews and approves live access.'],
            ['rocket-takeoff', 'Go live', 'Create live keys, switch your integration and start accepting real payments.'],
        ] as $i => [$icon, $title, $text])
            <div class="d-flex gap-4 mb-4 fade-up">
                <div class="text-center">
                    <span class="step-number">{{ $i + 1 }}</span>
                </div>
                <div class="card flex-grow-1"><div class="card-body p-4">
                    <h2 class="h5 fw-bold"><i class="bi bi-{{ $icon }} text-brand me-2"></i>{{ $title }}</h2>
                    <p class="text-muted-2 mb-0">{{ $text }}</p>
                </div></div>
            </div>
        @endforeach

        <div class="card mt-5"><div class="card-body p-4">
            <h2 class="h5 fw-bold mb-3">Payment statuses</h2>
            <p class="small text-muted-2">Provider-specific statuses are mapped to one consistent set:</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach (\App\Enums\PaymentStatus::cases() as $s)
                    <x-status-badge :status="$s" />
                @endforeach
            </div>
            <h2 class="h5 fw-bold mb-3 mt-4">Payout statuses</h2>
            <div class="d-flex flex-wrap gap-2">
                @foreach (\App\Enums\PayoutStatus::cases() as $s)
                    <x-status-badge :status="$s" />
                @endforeach
            </div>
        </div></div>
    </div>
</section>
@endsection

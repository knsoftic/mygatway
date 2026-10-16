@extends('layouts.public', ['title' => 'Features'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'Features', 'heading' => 'A complete crypto payments platform', 'intro' => 'Everything you need to accept and send crypto, run your business on accurate numbers, and stay in control.'])

<section class="section pt-4">
    <div class="container">
        @foreach ([
            'Payments' => [
                ['arrow-down-left-circle', 'Hosted checkout', 'Create a payment and redirect customers to the provider\'s secure hosted checkout. No wallet code on your side.'],
                ['arrow-up-right-circle', 'Payouts with approval', 'Request payouts by API or dashboard. Funds are reserved instantly and every payout is reviewed before sending.'],
                ['arrow-counterclockwise', 'Refunds', 'Full refunds of settled payments where the provider supports them, recorded in the ledger.'],
                ['hourglass-split', 'Expiry handling', 'Unpaid invoices expire automatically and are confirmed with the provider before closing.'],
            ],
            'Money & accounting' => [
                ['journal-check', 'Double-entry ledger', 'Available, reserved and pending balances per currency, each backed by immutable journal entries.'],
                ['percent', 'Configurable fees', 'Percentage, fixed, min/max, per merchant, provider and currency, with effective dates and a full audit trail.'],
                ['currency-exchange', 'No mixed currencies', 'Every amount is shown with its currency. Different currencies are never added together.'],
                ['arrow-repeat', 'Reconciliation', 'Automated ledger integrity checks and provider-side status comparison with review queues.'],
            ],
            'Developers' => [
                ['braces', 'Unified REST API', 'Consistent endpoints and error codes across providers, cursor pagination and JSON everywhere.'],
                ['broadcast', 'Signed webhooks', 'HMAC-SHA256 signatures, event IDs, retries with backoff and manual re-delivery.'],
                ['key', 'API key management', 'Test and live keys, scopes, IP allowlists, rotation, revocation and usage logs.'],
                ['cone-striped', 'Sandbox', 'Test mode simulates paid, underpaid, failed and expired payments without touching real funds.'],
            ],
            'Security & compliance' => [
                ['shield-lock', 'Two-factor authentication', 'TOTP-based 2FA for merchants; mandatory for administrators.'],
                ['fingerprint', 'Session & login monitoring', 'Login history, active sessions and suspicious activity detection.'],
                ['clipboard-data', 'Audit logs', 'Every sensitive action, from fee changes to payout approvals, is recorded and immutable.'],
                ['patch-check', 'Merchant verification', 'Business verification and admin approval before live payments are enabled.'],
            ],
        ] as $group => $items)
            <h2 class="h4 fw-bold mb-3 mt-4">{{ $group }}</h2>
            <div class="row g-4 mb-3">
                @foreach ($items as [$icon, $title, $text])
                    <div class="col-md-6 col-lg-3">
                        <div class="card feature-card"><div class="card-body p-4">
                            <div class="feature-icon mb-3"><i class="bi bi-{{ $icon }}"></i></div>
                            <h3 class="h6 fw-bold">{{ $title }}</h3>
                            <p class="small text-muted-2 mb-0">{{ $text }}</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
@endsection

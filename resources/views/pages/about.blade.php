@extends('layouts.public', ['title' => 'About us'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'About us', 'heading' => 'Making crypto payments simple and accountable', 'intro' => config('app.name').' gives businesses one secure integration for accepting and sending crypto through established payment providers.'])

<section class="section pt-4">
    <div class="container" style="max-width:56rem">
        <div class="row g-4">
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h2 class="h5 fw-bold"><i class="bi bi-bullseye text-brand me-2"></i>Our mission</h2>
                <p class="text-muted-2 mb-0">Let any business add crypto payments without building wallet infrastructure, while keeping every amount, fee and balance verifiable.</p>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h2 class="h5 fw-bold"><i class="bi bi-diagram-3 text-brand me-2"></i>How we work</h2>
                <p class="text-muted-2 mb-0">We do not run our own blockchain nodes or custody keys for customer wallets. Payments are processed by licensed or registered providers we integrate with through their official APIs.</p>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h2 class="h5 fw-bold"><i class="bi bi-shield-check text-brand me-2"></i>Security</h2>
                <p class="text-muted-2 mb-0">Signed callbacks, server-side fee calculation, an immutable double-entry ledger, two-factor authentication and complete audit trails.</p>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h2 class="h5 fw-bold"><i class="bi bi-people text-brand me-2"></i>Responsible growth</h2>
                <p class="text-muted-2 mb-0">Merchants are verified before going live, transactions are monitored, and we follow the terms of every provider we work with.</p>
            </div></div></div>
        </div>
        <div class="text-center mt-5">
            <a href="{{ route('contact') }}" class="btn btn-primary">Get in touch</a>
        </div>
    </div>
</section>
@endsection

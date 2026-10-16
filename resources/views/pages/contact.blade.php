@extends('layouts.public', ['title' => 'Contact us'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'Contact', 'heading' => 'Talk to our team', 'intro' => 'Questions about integration, pricing or verification? Send us a message and we will reply by email.'])

<section class="section pt-4">
    <div class="container" style="max-width:56rem">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card"><div class="card-body p-4">
                    @include('partials.flash')
                    <form method="POST" action="{{ route('contact.store') }}" novalidate>
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="c-name">Name</label>
                                <input id="c-name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required maxlength="120" autocomplete="name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="c-email">Email</label>
                                <input id="c-email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="c-company">Company <span class="text-muted-2">(optional)</span></label>
                                <input id="c-company" name="company" value="{{ old('company') }}" class="form-control" maxlength="120" autocomplete="organization">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="c-subject">Topic</label>
                                <select id="c-subject" name="subject" class="form-select @error('subject') is-invalid @enderror" required>
                                    @foreach (['Sales & pricing', 'Integration support', 'Verification', 'Payout question', 'Other'] as $topic)
                                        <option @selected(old('subject') === $topic)>{{ $topic }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="c-message">Message</label>
                                <textarea id="c-message" name="message" rows="5" class="form-control @error('message') is-invalid @enderror" required maxlength="5000">{{ old('message') }}</textarea>
                            </div>
                            {{-- Honeypot: humans leave this empty. --}}
                            <div class="d-none" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit" data-loading>
                                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Send message</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100"><div class="card-body p-4">
                    <h2 class="h6 fw-bold mb-3">Other ways to reach us</h2>
                    <p class="small mb-2"><i class="bi bi-envelope text-brand me-2"></i>{{ \App\Models\SystemSetting::get('platform.support_email') }}</p>
                    <p class="small mb-4"><i class="bi bi-book text-brand me-2"></i><a href="{{ route('docs.index') }}">Developer documentation</a></p>
                    <p class="small text-muted-2 mb-0">Never share API secrets, passwords or 2FA codes with anyone, including our support team.</p>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection

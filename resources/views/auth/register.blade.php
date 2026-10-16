@extends('layouts.auth', ['title' => 'Create account'])

@section('content')
    <h1 class="h4 fw-bold mb-1">Create your merchant account</h1>
    <p class="text-muted-2 small mb-4">Start in test mode right away. Live payments unlock after business verification.</p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Your full name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name" autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Work email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="business_name" class="form-label">Business name</label>
                <input id="business_name" name="business_name" value="{{ old('business_name') }}" class="form-control @error('business_name') is-invalid @enderror" required autocomplete="organization">
                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="business_website" class="form-label">Website <span class="text-muted-2">(optional)</span></label>
                <input id="business_website" type="url" name="business_website" value="{{ old('business_website') }}" placeholder="https://" class="form-control @error('business_website') is-invalid @enderror">
                @error('business_website')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password" aria-describedby="pwHelp">
            <div id="pwHelp" class="form-text">At least 10 characters with upper and lower case letters and a number.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" {{ old('terms') ? 'checked' : '' }}>
            <label class="form-check-label small" for="terms">
                I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms</a>,
                <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a> and
                <a href="{{ route('refund-policy') }}" target="_blank" rel="noopener">Refund &amp; Dispute Policy</a>.
            </label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Create account</span>
        </button>
    </form>
@endsection

@section('below')
    Already have an account? <a href="{{ route('login') }}">Log in</a>
@endsection

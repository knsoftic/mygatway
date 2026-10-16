@extends('layouts.auth', ['title' => 'Log in'])

@section('content')
    <h1 class="h4 fw-bold mb-1">Welcome back</h1>
    <p class="text-muted-2 small mb-4">Log in to manage payments, payouts and API keys.</p>

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="password" class="form-label">Password</label>
                <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            </div>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
        </div>
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Log in</span>
        </button>
    </form>
@endsection

@section('below')
    New to {{ config('app.name') }}? <a href="{{ route('register') }}">Create a merchant account</a>
@endsection

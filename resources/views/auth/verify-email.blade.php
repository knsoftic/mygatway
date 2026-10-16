@extends('layouts.auth', ['title' => 'Verify email'])

@section('content')
    <div class="text-center">
        <div class="feature-icon mb-3"><i class="bi bi-envelope-check"></i></div>
        <h1 class="h4 fw-bold mb-2">Verify your email</h1>
        <p class="text-muted-2 small mb-4">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to activate your dashboard.</p>
    </div>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Resend verification email</span>
        </button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-muted-2">Log out</button>
    </form>
@endsection

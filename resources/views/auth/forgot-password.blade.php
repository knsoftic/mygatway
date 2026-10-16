@extends('layouts.auth', ['title' => 'Reset password'])

@section('content')
    <h1 class="h4 fw-bold mb-1">Forgot your password?</h1>
    <p class="text-muted-2 small mb-4">Enter your account email and we will send you a secure reset link.</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Email reset link</span>
        </button>
    </form>
@endsection

@section('below')
    <a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to log in</a>
@endsection

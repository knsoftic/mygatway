@extends('layouts.auth', ['title' => 'Two-factor authentication'])

@section('content')
    <h1 class="h4 fw-bold mb-1">Two-factor authentication</h1>
    <p class="text-muted-2 small mb-4">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="code" class="form-label">Authentication code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" class="form-control form-control-lg text-center font-mono @error('code') is-invalid @enderror" autofocus maxlength="6">
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <details class="mb-4">
            <summary class="small text-muted-2">Use a recovery code instead</summary>
            <input name="recovery_code" class="form-control mt-2 font-mono @error('recovery_code') is-invalid @enderror" autocomplete="one-time-code" placeholder="xxxxxxxxxx-xxxxxxxxxx">
            @error('recovery_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </details>
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Verify</span>
        </button>
    </form>
@endsection

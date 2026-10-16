@extends('layouts.auth', ['title' => 'Confirm password'])

@section('content')
    <h1 class="h4 fw-bold mb-1">Confirm your password</h1>
    <p class="text-muted-2 small mb-4">This is a sensitive area. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100" data-loading>
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Confirm</span>
        </button>
    </form>
@endsection

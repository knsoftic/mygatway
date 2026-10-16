<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body class="hero">
<div class="position-absolute top-0 end-0 p-3">
    <button type="button" class="btn btn-link text-body" data-theme-toggle aria-label="Toggle dark mode"><i data-theme-icon class="bi bi-moon-stars"></i></button>
</div>
<main class="auth-wrap">
    <div class="auth-card fade-up">
        <a href="{{ route('home') }}" class="d-flex align-items-center justify-content-center gap-2 fw-bold fs-5 text-body text-decoration-none mb-4">
            <span class="brand-mark"><i class="bi bi-shield-check"></i></span>{{ config('app.name') }}
        </a>
        <div class="card">
            <div class="card-body p-4 p-md-5">
                @include('partials.flash', ['hideErrorSummary' => true])
                @yield('content')
            </div>
        </div>
        @hasSection('below')
            <div class="text-center small mt-3">@yield('below')</div>
        @endif
    </div>
</main>
</body>
</html>

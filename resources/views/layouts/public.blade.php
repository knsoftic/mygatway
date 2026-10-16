<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['title' => $title ?? null, 'description' => $description ?? null])
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable position-absolute p-2 bg-primary text-white" href="#main">Skip to content</a>

<nav class="navbar navbar-expand-xl site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('home') }}">
            <span class="brand-mark"><i class="bi bi-shield-check"></i></span>{{ config('app.name') }}
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="siteNav">
            <ul class="navbar-nav mx-auto gap-xl-1 text-nowrap">
                @foreach ([
                    'features' => 'Features',
                    'how-it-works' => 'How it works',
                    'payment-methods' => 'Payment methods',
                    'pricing' => 'Pricing',
                    'docs.index' => 'Developers',
                    'contact' => 'Contact',
                ] as $routeName => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($routeName === 'docs.index' ? 'docs.*' : $routeName) ? 'active fw-semibold' : '' }}" href="{{ route($routeName) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-xl-0">
                <button type="button" class="btn btn-link text-body px-2" data-theme-toggle aria-label="Toggle dark mode"><i data-theme-icon class="bi bi-moon-stars"></i></button>
                @auth
                    <a href="{{ auth()->user()->merchant_id ? route('dashboard') : route('admin.dashboard') }}" class="btn btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-link text-body text-decoration-none">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Get started</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<main id="main" class="flex-grow-1">
    @yield('content')
</main>

<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 fw-bold mb-2"><span class="brand-mark"><i class="bi bi-shield-check"></i></span>{{ config('app.name') }}</div>
                <p class="small text-muted-2 mb-0">Crypto Pay In and Pay Out for businesses, connected to Cryptomus and Binance Pay through one API.</p>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fw-semibold small mb-2">Product</div>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('features') }}">Features</a></li>
                    <li><a href="{{ route('how-it-works') }}">How it works</a></li>
                    <li><a href="{{ route('payment-methods') }}">Payment methods</a></li>
                    <li><a href="{{ route('pricing') }}">Pricing</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fw-semibold small mb-2">Developers</div>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('docs.index') }}">Documentation</a></li>
                    <li><a href="{{ route('docs.show', 'integration-guide') }}">Integration guide</a></li>
                    <li><a href="{{ route('docs.show', 'api-reference') }}">API reference</a></li>
                    <li><a href="{{ route('docs.show', 'webhooks') }}">Webhooks</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fw-semibold small mb-2">Company</div>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('about') }}">About us</a></li>
                    <li><a href="{{ route('faq') }}">FAQ</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fw-semibold small mb-2">Legal</div>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('terms') }}">Terms</a></li>
                    <li><a href="{{ route('privacy') }}">Privacy</a></li>
                    <li><a href="{{ route('refund-policy') }}">Refunds &amp; disputes</a></li>
                </ul>
            </div>
        </div>
        <div class="border-top mt-4 pt-3 small text-muted-2 d-flex flex-column flex-md-row justify-content-between gap-2">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            <span>Payments are processed by the respective providers. {{ config('app.name') }} is not a bank.</span>
        </div>
    </div>
</footer>
@stack('scripts')
</body>
</html>

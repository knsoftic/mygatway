@php
    $user = auth()->user();
    $area = $area ?? (request()->routeIs('admin.*') ? 'admin' : 'merchant');

    $merchantNav = [
        'Overview' => [
            ['dashboard', 'speedometer2', 'Dashboard', 'dashboard'],
            ['merchant.payments.index', 'arrow-down-left-circle', 'Payments', 'merchant.payments.*'],
            ['merchant.payouts.index', 'arrow-up-right-circle', 'Payouts', 'merchant.payouts.*'],
            ['merchant.ledger.index', 'journal-text', 'Balance & ledger', 'merchant.ledger.*'],
            ['merchant.reports.index', 'file-earmark-spreadsheet', 'Reports', 'merchant.reports.*'],
        ],
        'Developers' => [
            ['merchant.api-keys.index', 'key', 'API keys', 'merchant.api-keys.*'],
            ['merchant.webhooks.index', 'broadcast', 'Webhooks', 'merchant.webhooks.*'],
            ['merchant.api-logs.index', 'activity', 'API logs', 'merchant.api-logs.*'],
            ['docs.index', 'book', 'Documentation', 'docs.*'],
        ],
        'Settings' => [
            ['merchant.profile.edit', 'building', 'Business profile', 'merchant.profile.*'],
            ['merchant.verification.show', 'patch-check', 'Verification', 'merchant.verification.*'],
            ['security.edit', 'shield-lock', 'Security', 'security.*'],
            ['merchant.notifications.edit', 'bell', 'Notifications', 'merchant.notifications.*'],
        ],
    ];

    $adminNav = [
        'Platform' => [
            ['admin.dashboard', 'speedometer2', 'Overview', 'admin.dashboard', null],
            ['admin.merchants.index', 'shop', 'Merchants', 'admin.merchants.*', 'merchants.view'],
            ['admin.verifications.index', 'patch-check', 'Verifications', 'admin.verifications.*', 'merchants.verify'],
            ['admin.risk.index', 'exclamation-diamond', 'Risk events', 'admin.risk.*', 'risk.manage'],
        ],
        'Money' => [
            ['admin.payments.index', 'arrow-down-left-circle', 'Pay In', 'admin.payments.*', 'payments.view'],
            ['admin.payouts.index', 'arrow-up-right-circle', 'Payouts', 'admin.payouts.*', 'payouts.view'],
            ['admin.fee-rules.index', 'percent', 'Fee rules', 'admin.fee-rules.*', 'fees.view'],
            ['admin.reconciliation.index', 'arrow-repeat', 'Reconciliation', 'admin.reconciliation.*', 'payments.reconcile'],
            ['admin.reports.index', 'file-earmark-spreadsheet', 'Reports', 'admin.reports.*', 'reports.export'],
        ],
        'System' => [
            ['admin.providers.index', 'plug', 'Providers', 'admin.providers.*', 'providers.manage'],
            ['admin.webhooks.index', 'broadcast', 'Webhook logs', 'admin.webhooks.*', 'webhooks.view'],
            ['admin.audit-logs.index', 'clipboard-data', 'Audit & API logs', 'admin.audit-logs.*', 'audit.view'],
            ['admin.users.index', 'people', 'Admin users', 'admin.users.*', 'users.manage'],
            ['admin.settings.edit', 'gear', 'Settings', 'admin.settings.*', 'settings.manage'],
        ],
    ];
    $nav = $area === 'admin' ? $adminNav : $merchantNav;
    $mode = $dashboardMode ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body>
<a class="visually-hidden-focusable position-absolute p-2 bg-primary text-white" href="#content">Skip to content</a>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="Main navigation">
        <a class="brand" href="{{ $area === 'admin' ? route('admin.dashboard') : route('dashboard') }}">
            <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
            <span>{{ config('app.name') }}</span>
            @if ($area === 'admin')<span class="badge text-bg-danger ms-auto">Admin</span>@endif
        </a>
        <nav class="nav flex-column">
            @foreach ($nav as $section => $items)
                <div class="nav-section">{{ $section }}</div>
                @foreach ($items as $item)
                    @php [$routeName, $icon, $label, $pattern] = $item; $ability = $item[4] ?? null; @endphp
                    @continue(! Route::has($routeName) || ($ability && ! $user->can($ability)))
                    <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($routeName) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <i class="bi bi-{{ $icon }}"></i>{{ $label }}
                    </a>
                @endforeach
            @endforeach
        </nav>
        @if ($user->isAdmin() && $user->merchant_id)
            <div class="nav-section">Switch</div>
            <a class="nav-link" href="{{ $area === 'admin' ? route('dashboard') : route('admin.dashboard') }}"><i class="bi bi-arrow-left-right"></i>{{ $area === 'admin' ? 'Merchant dashboard' : 'Admin panel' }}</a>
        @endif
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="app-main">
        @if ($area === 'merchant' && $mode?->value === 'test')
            <div class="test-banner text-center py-1 px-3"><i class="bi bi-cone-striped"></i> You are viewing <strong>test mode</strong> data. Test payments use the sandbox simulator and never move real funds.</div>
        @endif
        <header class="app-topbar d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-sidebar-toggle aria-label="Open navigation"><i class="bi bi-list"></i></button>
            <div class="me-auto d-none d-md-block small text-muted-2">
                @if ($area === 'merchant' && $user->merchant)
                    <i class="bi bi-shop me-1"></i>{{ $user->merchant->name }}
                    <x-status-badge :status="$user->merchant->status" class="ms-1" />
                @else
                    <i class="bi bi-shield-lock me-1"></i>Platform administration
                @endif
            </div>
            @if ($area === 'merchant' && $mode && Route::has('merchant.mode.update'))
                <form method="POST" action="{{ route('merchant.mode.update') }}" class="d-flex align-items-center">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode->value === 'live' ? 'test' : 'live' }}">
                    <div class="form-check form-switch m-0" title="Switch between test and live data">
                        <input class="form-check-input" type="checkbox" role="switch" id="modeSwitch" {{ $mode->value === 'live' ? 'checked' : '' }} data-autosubmit>
                        <label class="form-check-label mode-pill {{ $mode->value === 'live' ? 'text-success' : 'text-warning' }}" for="modeSwitch">{{ $mode->label() }}</label>
                    </div>
                    <noscript><button class="btn btn-sm btn-link">Switch</button></noscript>
                </form>
            @endif
            <button type="button" class="btn btn-link text-body px-2" data-theme-toggle aria-label="Toggle dark mode"><i data-theme-icon class="bi bi-moon-stars"></i></button>
            @php $unread = $user->unreadNotifications()->count(); @endphp
            @if (Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}" class="btn btn-link text-body px-2 position-relative" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    @if ($unread)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
                </a>
            @endif
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i><span class="d-none d-sm-inline">{{ \Illuminate\Support\Str::limit($user->name, 18) }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="px-3 py-2 small text-muted-2">{{ $user->email }}</li>
                    <li><hr class="dropdown-divider"></li>
                    @if (Route::has('security.edit'))<li><a class="dropdown-item" href="{{ route('security.edit') }}"><i class="bi bi-shield-lock me-2"></i>Security</a></li>@endif
                    <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-house me-2"></i>Website</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button></form>
                    </li>
                </ul>
            </div>
        </header>

        <main id="content" class="app-content">
            @include('partials.flash')
            @hasSection('header')
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    @yield('header')
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>

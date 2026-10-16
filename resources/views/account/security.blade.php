@extends('layouts.app', ['title' => 'Security', 'area' => $area])

@section('header')
    <h1 class="page-title">Account security</h1>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-7">
        {{-- Two-factor authentication --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-lock me-1"></i> Two-factor authentication</span>
                @if ($user->hasTwoFactorEnabled())<span class="badge badge-soft text-bg-success">Enabled</span>@else<span class="badge badge-soft text-bg-warning">Not enabled</span>@endif
            </div>
            <div class="card-body">
                @if (! $user->two_factor_secret)
                    <p class="text-muted-2">Protect your account with a time-based code from an authenticator app (Google Authenticator, 1Password, Authy...). Required for administrators and for live API keys.</p>
                    <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="btn btn-primary"><i class="bi bi-shield-plus"></i> Enable two-factor authentication</button></form>
                @elseif (! $user->two_factor_confirmed_at)
                    <p class="mb-2">1. Scan this QR code with your authenticator app:</p>
                    <div class="bg-white d-inline-block p-2 rounded mb-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <p class="small text-muted-2">Can't scan? Enter this key manually: <span class="font-mono">{{ decrypt($user->two_factor_secret) }}</span></p>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="row g-2 align-items-end" style="max-width:24rem">
                        @csrf
                        <div class="col-8"><label class="form-label" for="tf-code">2. Enter the 6-digit code</label>
                            <input id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="form-control font-mono @error('code', 'confirmTwoFactorAuthentication') is-invalid @enderror" required>
                            @error('code', 'confirmTwoFactorAuthentication')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4"><button class="btn btn-primary w-100">Confirm</button></div>
                    </form>
                @else
                    <p class="text-muted-2">Two-factor authentication is active. Keep your recovery codes somewhere safe; each can be used once if you lose your device.</p>
                    @if (session('status') === 'recovery-codes-generated' || session('show_recovery_codes'))
                        <div class="bg-body-tertiary rounded p-3 font-mono small mb-3 row row-cols-2 g-1">
                            @foreach ($user->recoveryCodes() as $code)<div class="col">{{ $code }}</div>@endforeach
                        </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('security.recovery-codes') }}" class="btn btn-outline-secondary btn-sm">Show recovery codes</a>
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Regenerate codes</button></form>
                        @unless ($user->isAdmin())
                            <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Disable two-factor authentication? Live API key creation will be blocked.">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Disable</button></form>
                        @endunless
                    </div>
                @endif
            </div>
        </div>

        {{-- Password --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-key me-1"></i> Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('user-password.update') }}" class="row g-3" style="max-width:32rem">
                    @csrf @method('PUT')
                    <div class="col-12"><label class="form-label" for="pw-current">Current password</label>
                        <input id="pw-current" type="password" name="current_password" autocomplete="current-password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" required>
                        @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="pw-new">New password</label>
                        <input id="pw-new" type="password" name="password" autocomplete="new-password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" required>
                        @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="pw-confirm">Confirm</label>
                        <input id="pw-confirm" type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required></div>
                    <div class="col-12"><button class="btn btn-primary">Update password</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        {{-- Sessions --}}
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-laptop me-1"></i> Active sessions</div>
            <ul class="list-group list-group-flush">
                @foreach ($sessions as $s)
                    <li class="list-group-item bg-transparent d-flex justify-content-between">
                        <div><div class="fw-semibold small">{{ $s->agent }}</div><div class="small text-muted-2">{{ $s->ip }} · {{ $s->last_active->diffForHumans() }}</div></div>
                        @if ($s->current)<span class="badge badge-soft text-bg-success align-self-center">This device</span>@endif
                    </li>
                @endforeach
            </ul>
            @if ($sessions->count() > 1)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('security.sessions.destroy') }}" class="d-flex gap-2">
                        @csrf @method('DELETE')
                        <input type="password" name="password" class="form-control form-control-sm @error('password', 'sessions') is-invalid @enderror" placeholder="Confirm password" required autocomplete="current-password" aria-label="Password">
                        <button class="btn btn-sm btn-outline-danger text-nowrap">Log out others</button>
                    </form>
                    @error('password', 'sessions')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>

        {{-- Login history --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Recent sign-in activity</div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>When</th><th>Event</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logins as $l)
                    <tr><td class="small">{{ $l->created_at->format('M j, H:i') }}</td>
                        <td><span class="badge badge-soft text-bg-{{ $l->successful ? 'success' : 'danger' }}">{{ ucfirst($l->event) }}</span></td>
                        <td class="small font-mono">{{ $l->ip_address }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted-2 small text-center py-3">No activity recorded.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

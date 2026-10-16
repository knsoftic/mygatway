@php
    // Fortify flashes short status codes; translate them into readable messages.
    $statusText = [
        'two-factor-authentication-enabled' => 'Scan the QR code, then confirm with a code from your authenticator app.',
        'two-factor-authentication-confirmed' => 'Two-factor authentication is now enabled.',
        'two-factor-authentication-disabled' => 'Two-factor authentication has been disabled.',
        'recovery-codes-generated' => 'New recovery codes have been generated.',
        'password-updated' => 'Your password has been updated.',
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];
@endphp
@foreach (['success' => 'check-circle', 'error' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'status' => 'info-circle'] as $key => $icon)
    @if (session($key))
        <div class="alert alert-{{ $key === 'error' ? 'danger' : ($key === 'status' ? 'info' : $key) }} d-flex gap-2 align-items-start fade-up" role="alert">
            <i class="bi bi-{{ $icon }} mt-1"></i>
            <div>{{ $statusText[session($key)] ?? session($key) }}</div>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrorSummary ?? false))
    <div class="alert alert-danger fade-up" role="alert">
        <div class="d-flex gap-2"><i class="bi bi-exclamation-octagon mt-1"></i><strong>Please fix the following:</strong></div>
        <ul class="mb-0 mt-1 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

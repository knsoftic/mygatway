@extends('layouts.app', ['title' => $merchant->name, 'area' => 'admin'])

@section('header')
    <div>
        <a href="{{ route('admin.merchants.index') }}" class="small"><i class="bi bi-arrow-left"></i> Merchants</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap">{{ $merchant->name }} <x-status-badge :status="$merchant->status" /> <x-status-badge :status="$merchant->verification_status" /></h1>
        <div class="small text-muted-2 font-mono">{{ $merchant->public_id }}</div>
    </div>
    @can('fees.manage')
        <a class="btn btn-outline-secondary" href="{{ route('admin.fee-rules.create', ['merchant_id' => $merchant->id]) }}"><i class="bi bi-percent"></i> Add fee rule</a>
    @endcan
@endsection

@section('content')
    @if (session('merchant_setup_link'))
        <div class="alert alert-info">
            <strong><i class="bi bi-link-45deg"></i> Password setup link for {{ $merchant->owner?->email }}</strong>
            <div class="input-group mt-2"><input id="setup-link" class="form-control font-mono small" value="{{ session('merchant_setup_link') }}" readonly aria-label="Password setup link"><button class="btn btn-outline-secondary" data-copy="#setup-link" type="button"><i class="bi bi-clipboard"></i> Copy</button></div>
            <div class="small mt-2">Send it to the owner privately. It is shown only now, works once and expires after {{ config('auth.passwords.users.expire', 60) }} minutes. After that they can use "Forgot password" on the login page.</div>
        </div>
    @endif
    @if ($merchant->status_reason)
        <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Status reason: <strong>{{ $merchant->status_reason }}</strong></div>
    @endif
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Business</div><div class="card-body">
            @php $profile = $merchant->profile; @endphp
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted-2 fw-normal">Business email</dt><dd class="col-sm-8">{{ $merchant->business_email ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Website</dt><dd class="col-sm-8 text-break">@if ($merchant->business_website)<a href="{{ $merchant->business_website }}" target="_blank" rel="noopener noreferrer nofollow">{{ $merchant->business_website }}</a>@else — @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Owner</dt><dd class="col-sm-8">{{ $merchant->owner?->name ?? '—' }} <span class="text-muted-2">{{ $merchant->owner?->email }}</span></dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Legal name</dt><dd class="col-sm-8">{{ $profile?->legal_name ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Registration no.</dt><dd class="col-sm-8">{{ $profile?->registration_number ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Address</dt><dd class="col-sm-8">@if ($profile?->address_line1){{ collect([$profile->address_line1, $profile->address_line2, $profile->city, $profile->state, $profile->postal_code, $profile->country])->filter()->implode(', ') }}@else — @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Contact</dt><dd class="col-sm-8">{{ $profile?->contact_name ?? '—' }}@if ($profile?->contact_phone) · {{ $profile->contact_phone }}@endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Live mode</dt><dd class="col-sm-8">@if ($merchant->live_enabled)<i class="bi bi-check-circle-fill text-success"></i> Enabled {{ $merchant->live_enabled_at?->toFormattedDateString() }}@else Disabled @endif</dd>
                <dt class="col-sm-4 text-muted-2 fw-normal">Joined</dt><dd class="col-sm-8">{{ $merchant->created_at->toDayDateTimeString() }}</dd>
            </dl>
            @if ($profile?->business_description)
                <div class="mt-3 small"><div class="fw-semibold mb-1">Business activity</div><div class="text-muted-2">{{ $profile->business_description }}</div></div>
            @endif
        </div></div>

        <div class="card mb-4"><div class="card-header">Recent live payments</div>
            @if ($payments->isEmpty())
                <x-empty-state icon="receipt" title="No live payments yet" />
            @else
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Payment</th><th>Provider</th><th class="text-end">Amount</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>@foreach ($payments as $p)
                        <tr><td class="font-mono small"><a href="{{ route('admin.payments.show', $p) }}">{{ $p->public_id }}</a></td><td>{{ $p->provider->name }}</td>
                            <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td><td><x-status-badge :status="$p->status" /></td>
                            <td class="small text-muted-2 text-nowrap">{{ $p->created_at->format('M j, H:i') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>

        <div class="card mb-4"><div class="card-header">Recent live payouts</div>
            @if ($payouts->isEmpty())
                <x-empty-state icon="send" title="No live payouts yet" />
            @else
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Payout</th><th>Provider</th><th class="text-end">Amount</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>@foreach ($payouts as $p)
                        <tr><td class="font-mono small"><a href="{{ route('admin.payouts.show', $p) }}">{{ $p->public_id }}</a></td><td>{{ $p->provider->name }}</td>
                            <td class="text-end"><x-money :amount="$p->amount" :currency="$p->currency" /></td><td><x-status-badge :status="$p->status" /></td>
                            <td class="small text-muted-2 text-nowrap">{{ $p->created_at->format('M j, H:i') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>

        <div class="card mb-4"><div class="card-header">Merchant-specific fee rules</div>
            @if ($feeRules->isEmpty())
                <x-empty-state icon="percent" title="Using global fee rules" text="No rule targets this merchant directly." />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($feeRules as $r)
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <div class="small"><span class="fw-semibold">{{ $r->name }}</span> · {{ $r->direction->label() }} · {{ $r->provider?->name ?? 'Any provider' }} · {{ $r->currency ?? 'Any currency' }}
                                <div class="text-muted-2">{{ $r->describe() }} · from {{ $r->effective_from->toFormattedDateString() }}@if ($r->effective_until) to {{ $r->effective_until->toFormattedDateString() }}@endif</div></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge badge-soft text-bg-{{ $r->is_active ? 'success' : 'secondary' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span>
                                @can('fees.manage')<a href="{{ route('admin.fee-rules.edit', $r) }}" class="btn btn-sm btn-outline-secondary" aria-label="Edit rule"><i class="bi bi-pencil"></i></a>@endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card"><div class="card-header">API keys</div>
            @if ($merchant->apiKeys->isEmpty())
                <x-empty-state icon="key" title="No API keys created" />
            @else
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Name</th><th>Key ID</th><th>Mode</th><th>Last used</th><th>State</th></tr></thead>
                    <tbody>@foreach ($merchant->apiKeys as $k)
                        <tr><td>{{ $k->name }}</td><td class="font-mono small">{{ $k->key_id }}</td><td>{{ ucfirst(is_object($k->mode) ? $k->mode->value : $k->mode) }}</td>
                            <td class="small text-muted-2">{{ $k->last_used_at?->diffForHumans() ?? 'Never' }}@if ($k->last_used_ip) · {{ $k->last_used_ip }}@endif</td>
                            <td><span class="badge badge-soft text-bg-{{ $k->isActive() ? 'success' : 'secondary' }}">{{ $k->isActive() ? 'Active' : ($k->revoked_at ? 'Revoked' : 'Expired') }}</span></td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header">Balances</div><div class="card-body">
            @foreach (['live' => 'Live', 'test' => 'Test'] as $key => $label)
                <div class="small fw-semibold mb-1 {{ $loop->first ? '' : 'mt-3' }}">{{ $label }}</div>
                @if (empty($balances[$key]))
                    <div class="small text-muted-2">No ledger activity.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Currency</th><th class="text-end">Available</th><th class="text-end">Reserved</th></tr></thead>
                        <tbody>@foreach ($balances[$key] as $cur => $b)
                            <tr><td class="fw-semibold">{{ $cur }}</td><td class="text-end"><x-money :amount="$b['available']" /></td><td class="text-end"><x-money :amount="$b['reserved']" /></td></tr>
                        @endforeach</tbody>
                    </table>
                @endif
            @endforeach
        </div></div>

        @can('merchants.manage')
            <div class="card mb-4"><div class="card-header">Account status</div><div class="card-body">
                <form method="POST" action="{{ route('admin.merchants.status', $merchant) }}" class="mb-3" data-confirm="Change this merchant's status?">
                    @csrf @method('PUT')
                    <div class="row g-2">
                        <div class="col-sm-5"><label class="form-label small mb-1" for="status">Status</label>
                            <select id="status" name="status" class="form-select form-select-sm">@foreach (\App\Enums\MerchantStatus::cases() as $s)<option value="{{ $s->value }}" @selected($merchant->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
                        <div class="col-sm-7"><label class="form-label small mb-1" for="reason">Reason <span class="text-muted-2">(required to restrict/suspend)</span></label>
                            <input id="reason" name="reason" maxlength="255" class="form-control form-control-sm @error('reason') is-invalid @enderror" value="{{ old('reason') }}">@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Update status</span></button>
                </form>
                @php $canGoLive = $merchant->verification_status === \App\Enums\VerificationStatus::Approved; @endphp
                <form method="POST" action="{{ route('admin.merchants.live', $merchant) }}" data-confirm="{{ $merchant->live_enabled ? 'Disable live mode? Live API keys will stop working.' : 'Enable live transactions for this merchant?' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="live_enabled" value="{{ $merchant->live_enabled ? 0 : 1 }}">
                    <button class="btn btn-sm {{ $merchant->live_enabled ? 'btn-outline-danger' : 'btn-outline-success' }}" @disabled(! $merchant->live_enabled && ! $canGoLive)>
                        <i class="bi bi-{{ $merchant->live_enabled ? 'pause-circle' : 'play-circle' }}"></i> {{ $merchant->live_enabled ? 'Disable live mode' : 'Enable live mode' }}</button>
                    @if (! $merchant->live_enabled && ! $canGoLive)
                        <div class="form-text">Live mode needs an approved verification first.</div>
                    @endif
                </form>
            </div></div>
        @endcan

        <div class="card mb-4"><div class="card-header">Transaction limits</div>
            @if ($merchant->limits->isEmpty())
                <div class="card-body small text-muted-2">No merchant-specific limits; platform defaults apply.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($merchant->limits as $l)
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <div class="small"><span class="fw-semibold">{{ $l->direction === 'payin' ? 'Pay In' : 'Pay Out' }} · {{ $l->currency }}</span>
                                <div class="text-muted-2">Per transaction: {{ $l->max_per_transaction ? \App\Support\Decimal::format($l->max_per_transaction) : '—' }} · Daily: {{ $l->max_daily_total ? \App\Support\Decimal::format($l->max_daily_total) : '—' }}</div></div>
                            @can('merchants.manage')
                                <form method="POST" action="{{ route('admin.merchants.limits.destroy', [$merchant, $l]) }}" data-confirm="Remove this limit?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="Remove limit"><i class="bi bi-trash"></i></button></form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
            @can('merchants.manage')
                <form method="POST" action="{{ route('admin.merchants.limits.store', $merchant) }}" class="card-body border-top row g-2 align-items-end">
                    @csrf
                    <div class="col-6"><label class="form-label small mb-1" for="ldir">Direction</label><select id="ldir" name="direction" class="form-select form-select-sm"><option value="payin">Pay In</option><option value="payout">Pay Out</option></select></div>
                    <div class="col-6"><label class="form-label small mb-1" for="lcur">Currency</label><input id="lcur" name="currency" class="form-control form-control-sm text-uppercase @error('currency') is-invalid @enderror" required maxlength="16" placeholder="USDT"></div>
                    <div class="col-6"><label class="form-label small mb-1" for="lmax">Max per transaction</label><input id="lmax" name="max_per_transaction" inputmode="decimal" class="form-control form-control-sm @error('max_per_transaction') is-invalid @enderror"></div>
                    <div class="col-6"><label class="form-label small mb-1" for="lday">Max daily total</label><input id="lday" name="max_daily_total" inputmode="decimal" class="form-control form-control-sm @error('max_daily_total') is-invalid @enderror"></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary">Save limit</button></div>
                </form>
            @endcan
        </div>

        <div class="card mb-4"><div class="card-header">Verification history</div>
            @if ($merchant->verifications->isEmpty())
                <div class="card-body small text-muted-2">Nothing submitted yet.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($merchant->verifications as $v)
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <span class="small">#{{ $v->id }} · {{ $v->created_at->format('M j, Y') }}@if ($v->review_note)<div class="text-muted-2">{{ \Illuminate\Support\Str::limit($v->review_note, 80) }}</div>@endif</span>
                            <span class="d-flex gap-2 align-items-center"><x-status-badge :status="\App\Enums\VerificationStatus::tryFrom($v->status) ?? $v->status" />
                                @can('merchants.verify')<a href="{{ route('admin.verifications.show', $v) }}" class="btn btn-sm btn-outline-secondary">Open</a>@endcan</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card"><div class="card-header">Team members</div>
            <ul class="list-group list-group-flush">
                @foreach ($merchant->users as $u)
                    <li class="list-group-item bg-transparent small d-flex justify-content-between">
                        <span>{{ $u->name }}<div class="text-muted-2">{{ $u->email }}</div></span>
                        <span class="text-muted-2 text-end">@if ($u->two_factor_confirmed_at)<i class="bi bi-shield-check text-success" title="2FA enabled"></i>@endif Last login: {{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->diffForHumans() : 'never' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

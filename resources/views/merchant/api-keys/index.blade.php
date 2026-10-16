@extends('layouts.app', ['title' => 'API keys'])

@section('header')
    <div>
        <h1 class="page-title">API keys</h1>
        <div class="small text-muted-2">Authenticate server-to-server requests with <code>Authorization: Bearer sk_…</code>. Never expose secrets in browsers or mobile apps.</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKey"><i class="bi bi-plus-lg"></i> Create key</button>
@endsection

@section('content')
    @if ($newSecret)
        <div class="card border-success mb-4 fade-up">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-shield-lock-fill text-success fs-5"></i><strong>Your new {{ $newSecret['mode'] }} secret key</strong></div>
                <p class="small text-muted-2 mb-2">Store it in your server's environment variables or secret manager. For your security it is shown only once.</p>
                <div class="input-group">
                    <input id="new-secret" class="form-control font-mono secret-box" value="{{ $newSecret['secret'] }}" readonly aria-label="New API secret">
                    <button class="btn btn-outline-secondary" type="button" data-copy="#new-secret"><i class="bi bi-clipboard"></i> Copy</button>
                </div>
                <div class="small text-muted-2 mt-2">Key ID: <span class="font-mono">{{ $newSecret['key_id'] }}</span></div>
            </div>
        </div>
    @endif

    <div class="card">
        @if ($keys->isEmpty())
            <x-empty-state icon="key" title="No API keys yet" text="Create a test key to start integrating.">
                <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#createKey">Create your first key</button>
            </x-empty-state>
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Name</th><th>Mode</th><th>Key</th><th>Scopes</th><th>IP allowlist</th><th>Usage (30d)</th><th>Last used</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($keys as $key)
                    @php $u = $usage[$key->id] ?? null; @endphp
                    <tr class="{{ $key->isActive() ? '' : 'opacity-50' }}">
                        <td class="fw-semibold">{{ $key->name }}<div class="small text-muted-2 font-mono">{{ $key->key_id }}</div></td>
                        <td><span class="badge badge-soft text-bg-{{ $key->mode->value === 'live' ? 'success' : 'warning' }}">{{ $key->mode->label() }}</span></td>
                        <td class="font-mono small">{{ $key->maskedSecret() }}</td>
                        <td class="small">{{ implode(', ', $key->scopes ?? []) }}</td>
                        <td class="small">{{ $key->ip_allowlist ? implode(', ', $key->ip_allowlist) : 'Any' }}</td>
                        <td class="small">{{ number_format($u->total ?? 0) }} req @if (($u->errors ?? 0) > 0)<span class="text-danger">· {{ $u->errors }} errors</span>@endif<div class="text-muted-2">{{ $key->rate_limit_per_minute }}/min limit</div></td>
                        <td class="small">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}@if ($key->last_used_ip)<div class="text-muted-2">{{ $key->last_used_ip }}</div>@endif</td>
                        <td>@if ($key->revoked_at)<span class="badge badge-soft text-bg-danger">Revoked</span>@elseif (! $key->isActive())<span class="badge badge-soft text-bg-secondary">Expired</span>@else<span class="badge badge-soft text-bg-success">Active</span>@endif</td>
                        <td class="text-end text-nowrap">
                            @if ($key->isActive())
                                <form method="POST" action="{{ route('merchant.api-keys.rotate', $key) }}" class="d-inline" data-confirm="Rotate this key? The current secret stops working immediately.">
                                    @csrf<button class="btn btn-sm btn-outline-secondary" title="Rotate"><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                                <form method="POST" action="{{ route('merchant.api-keys.destroy', $key) }}" class="d-inline" data-confirm="Revoke this key? Requests using it will fail immediately.">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Revoke"><i class="bi bi-x-octagon"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="modal fade" id="createKey" tabindex="-1" aria-labelledby="createKeyLabel" aria-hidden="true">
        <div class="modal-dialog"><form method="POST" action="{{ route('merchant.api-keys.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="createKeyLabel">Create API key</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="k-name">Name</label><input id="k-name" name="name" class="form-control" required maxlength="100" placeholder="e.g. Production server" value="{{ old('name') }}"></div>
                <div class="mb-3"><label class="form-label d-block">Mode</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="mode" id="k-test" value="test" checked><label class="btn btn-outline-warning" for="k-test">Test</label>
                        <input type="radio" class="btn-check" name="mode" id="k-live" value="live" @disabled(! $canLive)><label class="btn btn-outline-success" for="k-live">Live</label>
                    </div>
                    @unless ($canLive)<div class="form-text">Live keys need approved verification and two-factor authentication.</div>@endunless
                </div>
                <div class="mb-3"><label class="form-label d-block">Permissions</label>
                    @foreach ($scopes as $scope)
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" id="s-{{ $loop->index }}" checked><label class="form-check-label small font-mono" for="s-{{ $loop->index }}">{{ $scope }}</label></div>
                    @endforeach
                </div>
                <div class="mb-3"><label class="form-label" for="k-ips">IP allowlist <span class="text-muted-2">(optional)</span></label>
                    <textarea id="k-ips" name="ip_allowlist" class="form-control font-mono" rows="2" placeholder="203.0.113.10, 198.51.100.0/24">{{ old('ip_allowlist') }}</textarea>
                    <div class="form-text">Only these IPs/CIDR ranges may use the key. Leave empty to allow any IP.</div></div>
                <div><label class="form-label" for="k-rate">Rate limit (requests/minute)</label><input id="k-rate" type="number" name="rate_limit_per_minute" class="form-control" min="10" max="{{ config('gateway.api.max_rate_limit_per_minute') }}" value="{{ old('rate_limit_per_minute', config('gateway.api.default_rate_limit_per_minute')) }}"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Create key</span></button></div>
        </form></div>
    </div>
@endsection

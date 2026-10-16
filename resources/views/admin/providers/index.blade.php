@extends('layouts.app', ['title' => 'Providers', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Payment providers</h1><div class="small text-muted-2">Enter each provider's API keys below, or set them in the server's .env file. Keys saved here are encrypted and take priority over .env.</div></div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.providers.health') }}">@csrf<button class="btn btn-outline-secondary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-heart-pulse"></i> Check health</span></button></form>
        <form method="POST" action="{{ route('admin.providers.sync') }}">@csrf<button class="btn btn-outline-secondary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-arrow-repeat"></i> Sync currencies</span></button></form>
    </div>
@endsection

@section('content')
<div class="row g-4">
    @foreach ($providers as $row)
        @php $p = $row['model']; $cap = $row['capabilities']; @endphp
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ $p->name }} <span class="text-muted-2 font-mono small">{{ $p->code }}</span></span>
                    <span class="badge badge-soft text-bg-{{ match ($p->health_status) { 'healthy' => 'success', 'degraded' => 'warning', 'down' => 'danger', default => 'secondary' } }}">{{ ucfirst($p->health_status ?? 'unknown') }}</span>
                </div>
                <div class="card-body">
                    <div class="small text-muted-2 mb-3">{{ $p->health_message ?? 'Not checked yet' }}@if ($p->health_checked_at) · {{ $p->health_checked_at->diffForHumans() }}@endif</div>

                    <div class="row g-2 small mb-3">
                        <div class="col-6"><i class="bi bi-{{ $row['configured'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i> API credentials</div>
                        <div class="col-6"><i class="bi bi-{{ $row['payoutConfigured'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' }}"></i> Payout credentials</div>
                    </div>

                    <div class="small fw-semibold mb-1">Supported by the provider API</div>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach (['Pay In' => $cap->payIn, 'Pay Out' => $cap->payOut, 'Refunds' => $cap->refund, 'Partial refunds' => $cap->partialRefund, 'Webhooks' => $cap->webhooks, 'Status query' => $cap->statusQuery, 'Provider sandbox' => $cap->providerSandbox] as $label => $yes)
                            <span class="badge badge-soft text-bg-{{ $yes ? 'success' : 'secondary' }}"><i class="bi bi-{{ $yes ? 'check' : 'x' }}"></i> {{ $label }}</span>
                        @endforeach
                    </div>
                    @if ($cap->payoutDestinationTypes)
                        <div class="small mb-2"><span class="text-muted-2">Payout destinations:</span> {{ collect($cap->payoutDestinationTypes)->map->label()->implode(', ') }}@if ($cap->payoutRequiresNetwork) · network required @endif</div>
                    @endif
                    @if ($cap->notes)
                        <ul class="small text-muted-2 ps-3 mb-3">@foreach ($cap->notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                    @endif

                    <div class="small mb-3"><span class="text-muted-2">Callback URL:</span> <code class="text-break">{{ $row['webhookUrl'] }}</code></div>

                    <form method="POST" action="{{ route('admin.providers.credentials', $p) }}" class="border-top pt-3 mb-3" autocomplete="off">
                        @csrf @method('PUT')
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold"><i class="bi bi-key"></i> API credentials</span>
                            @if ($p->credentials_updated_at)<span class="small text-muted-2">Updated {{ $p->credentials_updated_at->diffForHumans() }}</span>@endif
                        </div>
                        @foreach ($row['credentials'] as $field)
                            @php $inputId = 'cred-'.$p->code.'-'.$field['key']; @endphp
                            <div class="mb-2">
                                <label class="form-label small mb-1 d-flex justify-content-between flex-wrap gap-1" for="{{ $inputId }}">
                                    <span>{{ $field['label'] }}</span>
                                    <span>
                                        @if ($field['source'] === 'admin')<span class="badge badge-soft text-bg-success">Saved here</span>
                                        @elseif ($field['source'] === 'env')<span class="badge badge-soft text-bg-info" title="{{ $field['env'] }}">From .env</span>
                                        @else<span class="badge badge-soft text-bg-secondary">Not set</span>@endif
                                        @if ($field['hint'])<code class="ms-1">{{ $field['hint'] }}</code>@endif
                                    </span>
                                </label>
                                <input id="{{ $inputId }}" name="credentials[{{ $field['key'] }}]" type="{{ $field['secret'] ? 'password' : 'text' }}"
                                    class="form-control form-control-sm font-mono @error('credentials.'.$field['key'], 'credentials_'.$p->code) is-invalid @enderror"
                                    autocomplete="new-password" spellcheck="false" placeholder="{{ $field['source'] === 'none' ? 'Enter value' : 'Leave blank to keep current value' }}">
                                @error('credentials.'.$field['key'], 'credentials_'.$p->code)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if ($field['source'] === 'admin')
                                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove[]" value="{{ $field['key'] }}" id="{{ $inputId }}-rm">
                                        <label class="form-check-label small text-muted-2" for="{{ $inputId }}-rm">Remove saved value (falls back to .env)</label></div>
                                @endif
                            </div>
                        @endforeach
                        @error('credentials', 'credentials_'.$p->code)<div class="small text-danger">{{ $message }}</div>@enderror
                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-sm-8"><label class="form-label small mb-1" for="pw-{{ $p->code }}">Your password, to confirm</label>
                                <input id="pw-{{ $p->code }}" name="current_password" type="password" required autocomplete="current-password" class="form-control form-control-sm @error('current_password', 'credentials_'.$p->code) is-invalid @enderror">
                                @error('current_password', 'credentials_'.$p->code)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-sm-4"><button class="btn btn-sm btn-primary w-100" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Save &amp; test</span></button></div>
                        </div>
                        <div class="form-text">Stored encrypted. Saved values override .env and are never shown again in full. After saving, the connection is checked automatically.</div>
                    </form>

                    <form method="POST" action="{{ route('admin.providers.update', $p) }}" class="border-top pt-3" data-confirm="Save {{ $p->name }} settings?">
                        @csrf @method('PUT')
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-8 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="payin_enabled" value="1" id="pi{{ $p->id }}" @checked($p->payin_enabled) @disabled(! $cap->payIn)><label class="form-check-label small" for="pi{{ $p->id }}">Pay In</label></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="payout_enabled" value="1" id="po{{ $p->id }}" @checked($p->payout_enabled) @disabled(! $cap->payOut)><label class="form-check-label small" for="po{{ $p->id }}">Pay Out</label></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="refund_enabled" value="1" id="rf{{ $p->id }}" @checked($p->refund_enabled) @disabled(! $cap->refund)><label class="form-check-label small" for="rf{{ $p->id }}">Refunds</label></div>
                            </div>
                            <div class="col-sm-4"><label class="form-label small mb-1" for="pr{{ $p->id }}">Priority</label><input id="pr{{ $p->id }}" name="priority" type="number" min="1" max="1000" value="{{ $p->priority }}" class="form-control form-control-sm"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="{{ route('admin.providers.currencies', $p) }}" class="small">{{ $p->payin_count }} Pay In / {{ $p->payout_count }} Pay Out currencies <i class="bi bi-arrow-right"></i></a>
                            <button class="btn btn-sm btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

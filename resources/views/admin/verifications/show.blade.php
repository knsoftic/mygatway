@extends('layouts.app', ['title' => 'Verification #'.$v->id, 'area' => 'admin'])

@section('header')
    <div>
        <a href="{{ route('admin.verifications.index', ['status' => $v->status]) }}" class="small"><i class="bi bi-arrow-left"></i> Verifications</a>
        <h1 class="page-title mt-1 d-flex align-items-center gap-2 flex-wrap">{{ $v->merchant->name }} <x-status-badge :status="\App\Enums\VerificationStatus::tryFrom($v->status) ?? $v->status" /></h1>
        <div class="small text-muted-2">Verification #{{ $v->id }} · submitted {{ $v->created_at->toDayDateTimeString() }} by {{ $v->submitter?->name ?? 'unknown' }}</div>
    </div>
    @can('merchants.view')
        <a class="btn btn-outline-secondary" href="{{ route('admin.merchants.show', $v->merchant) }}"><i class="bi bi-shop"></i> Merchant profile</a>
    @endcan
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4"><div class="card-header">Submitted information</div><div class="card-body">
            @php
                $labels = ['legal_name' => 'Legal name', 'registration_number' => 'Registration number', 'country' => 'Country', 'address_line1' => 'Address', 'address_line2' => 'Address line 2',
                    'city' => 'City', 'state' => 'State / province', 'postal_code' => 'Postal code', 'contact_name' => 'Contact name', 'contact_phone' => 'Contact phone',
                    'support_email' => 'Support email', 'business_description' => 'Business activity'];
                $data = collect($v->submitted_data ?? [])->except(['declaration', 'document']);
            @endphp
            @if ($data->isEmpty())
                <div class="small text-muted-2">No form data stored with this submission.</div>
            @else
                <dl class="row small mb-0">
                    @foreach ($data as $key => $value)
                        <dt class="col-sm-4 text-muted-2 fw-normal">{{ $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</dt>
                        <dd class="col-sm-8 text-break">{{ is_scalar($value) && $value !== '' ? $value : (is_array($value) ? json_encode($value) : '—') }}</dd>
                    @endforeach
                </dl>
            @endif
        </div></div>

        <div class="card"><div class="card-header">Document</div><div class="card-body">
            @if ($v->document_path)
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small"><i class="bi bi-file-earmark-text"></i> {{ $v->document_original_name ?? basename($v->document_path) }}</span>
                    <a href="{{ route('admin.verifications.document', $v) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a>
                </div>
                <div class="form-text">Downloads are recorded in the audit log.</div>
            @else
                <div class="small text-muted-2">No document uploaded.</div>
            @endif
        </div></div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4"><div class="card-header">Merchant</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-5 text-muted-2 fw-normal">Merchant ID</dt><dd class="col-sm-7 font-mono">{{ $v->merchant->public_id }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Owner</dt><dd class="col-sm-7">{{ $v->merchant->owner?->name ?? '—' }}<div class="text-muted-2">{{ $v->merchant->owner?->email }}</div></dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Website</dt><dd class="col-sm-7 text-break">{{ $v->merchant->business_website ?? '—' }}</dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Account status</dt><dd class="col-sm-7"><x-status-badge :status="$v->merchant->status" /></dd>
                <dt class="col-sm-5 text-muted-2 fw-normal">Live mode</dt><dd class="col-sm-7">{{ $v->merchant->live_enabled ? 'Enabled' : 'Disabled' }}</dd>
            </dl>
        </div></div>

        @if ($v->status === 'submitted')
            <div class="card mb-4"><div class="card-header">Approve</div><div class="card-body">
                <form method="POST" action="{{ route('admin.verifications.approve', $v) }}" data-confirm="Approve this verification?">
                    @csrf
                    <div class="mb-2"><label class="form-label small mb-1" for="anote">Internal note <span class="text-muted-2">(optional)</span></label><textarea id="anote" name="note" rows="2" maxlength="1000" class="form-control form-control-sm"></textarea></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="enable_live" value="1" id="elive" checked><label class="form-check-label small" for="elive">Enable live transactions now</label></div>
                    <button class="btn btn-success" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-check2-circle"></i> Approve</span></button>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Reject</div><div class="card-body">
                <form method="POST" action="{{ route('admin.verifications.reject', $v) }}" data-confirm="Reject this verification? The merchant will see your note and can resubmit.">
                    @csrf
                    <div class="mb-2"><label class="form-label small mb-1" for="rnote">Reason shown to the merchant</label><textarea id="rnote" name="note" rows="3" maxlength="1000" required class="form-control form-control-sm @error('note') is-invalid @enderror">{{ old('note') }}</textarea>@error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <button class="btn btn-outline-danger" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label"><i class="bi bi-x-circle"></i> Reject</span></button>
                </form>
            </div></div>
        @else
            <div class="card"><div class="card-header">Review</div><div class="card-body small">
                <div><span class="text-muted-2">Reviewed by</span> {{ $v->reviewer?->name ?? '—' }} · {{ $v->reviewed_at?->toDayDateTimeString() ?? '—' }}</div>
                @if ($v->review_note)<div class="mt-2">{{ $v->review_note }}</div>@endif
            </div></div>
        @endif
    </div>
</div>
@endsection

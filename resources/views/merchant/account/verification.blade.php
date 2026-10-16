@extends('layouts.app', ['title' => 'Business verification'])

@section('header')
    <div><h1 class="page-title">Business verification</h1><div class="small text-muted-2">Required before live payments and payouts are enabled.</div></div>
    <x-status-badge :status="$merchant->verification_status" />
@endsection

@section('content')
<div style="max-width:52rem">
    @php $status = $merchant->verification_status->value; @endphp
    @if ($status === 'approved')
        <div class="alert alert-success"><i class="bi bi-patch-check-fill"></i> Your business is verified. @if ($merchant->isLiveEnabled()) Live mode is enabled - create live API keys to start accepting real payments. @else Live mode will be enabled by our team shortly. @endif</div>
    @elseif ($status === 'submitted')
        <div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Submitted {{ $latest?->created_at?->diffForHumans() }}. Our compliance team is reviewing your information.</div>
    @else
        @if ($status === 'rejected')
            <div class="alert alert-danger"><strong>Your previous submission was not approved.</strong> @if ($latest?->review_note)<div>Reason: {{ $latest->review_note }}</div>@endif You can correct the details and submit again.</div>
        @endif
        <form method="POST" action="{{ route('merchant.verification.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label" for="legal">Registered legal name</label><input id="legal" name="legal_name" value="{{ old('legal_name', $profile?->legal_name ?? $merchant->name) }}" class="form-control @error('legal_name') is-invalid @enderror" required></div>
                <div class="col-md-6"><label class="form-label" for="reg">Company registration number</label><input id="reg" name="registration_number" value="{{ old('registration_number', $profile?->registration_number) }}" class="form-control @error('registration_number') is-invalid @enderror" required></div>
                <div class="col-md-3"><label class="form-label" for="country">Country (ISO code)</label><input id="country" name="country" value="{{ old('country', $profile?->country) }}" class="form-control text-uppercase @error('country') is-invalid @enderror" maxlength="2" placeholder="PK" required></div>
                <div class="col-md-9"><label class="form-label" for="a1">Registered address</label><input id="a1" name="address_line1" value="{{ old('address_line1', $profile?->address_line1) }}" class="form-control @error('address_line1') is-invalid @enderror" required autocomplete="address-line1"></div>
                <div class="col-md-6"><label class="form-label" for="a2">Address line 2</label><input id="a2" name="address_line2" value="{{ old('address_line2', $profile?->address_line2) }}" class="form-control" autocomplete="address-line2"></div>
                <div class="col-md-6"><label class="form-label" for="city">City</label><input id="city" name="city" value="{{ old('city', $profile?->city) }}" class="form-control @error('city') is-invalid @enderror" required></div>
                <div class="col-md-6"><label class="form-label" for="state">State / province</label><input id="state" name="state" value="{{ old('state', $profile?->state) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label" for="zip">Postal code</label><input id="zip" name="postal_code" value="{{ old('postal_code', $profile?->postal_code) }}" class="form-control"></div>
                <div class="col-12"><label class="form-label" for="bdesc">Business activity</label><textarea id="bdesc" name="business_description" rows="3" class="form-control @error('business_description') is-invalid @enderror" required minlength="20">{{ old('business_description', $profile?->business_description) }}</textarea><div class="form-text">Describe your products/services, customers and expected monthly volume.</div></div>
                <div class="col-12"><label class="form-label" for="doc">Registration document</label><input id="doc" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="form-control @error('document') is-invalid @enderror" required><div class="form-text">PDF, JPG or PNG, up to 5 MB. Stored privately and only visible to our compliance team.</div></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input @error('declaration') is-invalid @enderror" type="checkbox" name="declaration" value="1" id="decl"><label class="form-check-label small" for="decl">I confirm the information is accurate and that my business does not engage in activities prohibited by the Terms or by our payment providers.</label></div></div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Submit for review</span></button></div>
        </form>
    @endif
</div>
@endsection

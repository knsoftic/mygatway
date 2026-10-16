@extends('layouts.app', ['title' => 'Business profile'])

@section('header')
    <div><h1 class="page-title">Business profile</h1><div class="small text-muted-2">Merchant ID <span class="font-mono">{{ $merchant->public_id }}</span></div></div>
    <div class="d-flex gap-2 align-items-center">
        <x-status-badge :status="$merchant->status" />
        <x-status-badge :status="$merchant->verification_status" />
        @if ($merchant->isLiveEnabled())<span class="badge badge-soft text-bg-success">Live enabled</span>@endif
    </div>
@endsection

@section('content')
    @if ($merchant->status_reason && $merchant->status->value !== 'active')
        <div class="alert alert-warning">Account status note: {{ $merchant->status_reason }}</div>
    @endif
    <form method="POST" action="{{ route('merchant.profile.update') }}" class="card" style="max-width:52rem">
        @csrf @method('PUT')
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Business name</label><input id="name" name="name" value="{{ old('name', $merchant->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="255" @readonly($merchant->verification_status->value === 'approved')></div>
            <div class="col-md-6"><label class="form-label" for="website">Business website</label><input id="website" type="url" name="business_website" value="{{ old('business_website', $merchant->business_website) }}" class="form-control @error('business_website') is-invalid @enderror" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label" for="bemail">Business email</label><input id="bemail" type="email" name="business_email" value="{{ old('business_email', $merchant->business_email) }}" class="form-control @error('business_email') is-invalid @enderror" required></div>
            <div class="col-md-6"><label class="form-label" for="semail">Support email (shown to customers)</label><input id="semail" type="email" name="support_email" value="{{ old('support_email', $profile?->support_email) }}" class="form-control @error('support_email') is-invalid @enderror"></div>
            <div class="col-md-6"><label class="form-label" for="cname">Contact person</label><input id="cname" name="contact_name" value="{{ old('contact_name', $profile?->contact_name) }}" class="form-control" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label" for="cphone">Contact phone</label><input id="cphone" name="contact_phone" value="{{ old('contact_phone', $profile?->contact_phone) }}" class="form-control @error('contact_phone') is-invalid @enderror" maxlength="40" autocomplete="tel"></div>
            <div class="col-12"><label class="form-label" for="desc">What does your business sell?</label><textarea id="desc" name="business_description" rows="3" class="form-control" maxlength="2000">{{ old('business_description', $profile?->business_description) }}</textarea></div>
        </div>
        <div class="card-footer bg-transparent text-end">
            @if (auth()->id() === $merchant->owner_user_id)<button class="btn btn-primary">Save changes</button>@else<span class="small text-muted-2">Only the account owner can edit business details.</span>@endif
        </div>
    </form>
@endsection

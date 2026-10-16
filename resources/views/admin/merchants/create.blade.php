@extends('layouts.app', ['title' => 'New merchant', 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.merchants.index') }}" class="small"><i class="bi bi-arrow-left"></i> Merchants</a><h1 class="page-title mt-1">New merchant</h1>
        <div class="small text-muted-2">Merchant accounts are created by the platform team. The owner sets their own password from a one-time link.</div></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('admin.merchants.store') }}" class="card" novalidate>
            @csrf
            <div class="card-body row g-3">
                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Business</div></div>
                <div class="col-md-6"><label class="form-label" for="business_name">Business name</label>
                    <input id="business_name" name="business_name" value="{{ old('business_name') }}" required maxlength="255" class="form-control @error('business_name') is-invalid @enderror">
                    @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="business_website">Website <span class="text-muted-2">(optional)</span></label>
                    <input id="business_website" name="business_website" type="url" value="{{ old('business_website') }}" maxlength="255" placeholder="https://" class="form-control @error('business_website') is-invalid @enderror">
                    @error('business_website')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Owner login</div></div>
                <div class="col-md-6"><label class="form-label" for="owner_name">Owner name</label>
                    <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required maxlength="255" autocomplete="off" class="form-control @error('owner_name') is-invalid @enderror">
                    @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="owner_email">Owner email</label>
                    <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required maxlength="255" autocomplete="off" class="form-control @error('owner_email') is-invalid @enderror">
                    @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">This is the login email and the business email.</div></div>
                <div class="col-md-6"><label class="form-label" for="contact_phone">Contact phone <span class="text-muted-2">(optional)</span></label>
                    <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" maxlength="40" class="form-control @error('contact_phone') is-invalid @enderror">
                    @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="status">Account status</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active (can use test mode now)</option>
                        <option value="pending" @selected(old('status') === 'pending')>Pending</option>
                    </select>
                    <div class="form-text">Live payments still need approved business verification.</div></div>

                <div class="col-12"><div class="form-check">
                    <input type="hidden" name="send_email" value="0">
                    <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" @checked(old('send_email'))>
                    <label class="form-check-label" for="send_email">Email the password setup link to the owner</label>
                    <div class="form-text">Only tick this if outgoing mail is configured on the server. Otherwise the link is shown to you once to share.</div>
                </div></div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                <a href="{{ route('admin.merchants.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Create merchant</span></button>
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card"><div class="card-header">What happens next</div><div class="card-body small">
            <ol class="ps-3 mb-0">
                <li class="mb-1">The merchant and its owner login are created. The owner's email counts as verified.</li>
                <li class="mb-1">The owner opens the setup link and chooses a password. The link expires after {{ config('auth.passwords.users.expire', 60) }} minutes; after that, use "Forgot password" on the login page.</li>
                <li class="mb-1">They can use test mode straight away.</li>
                <li>For live payments they submit business verification, which you approve under Verifications.</li>
            </ol>
        </div></div>
    </div>
</div>
@endsection

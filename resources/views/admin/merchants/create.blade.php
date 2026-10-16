@extends('layouts.app', ['title' => 'New merchant', 'area' => 'admin'])

@section('header')
    <div><a href="{{ route('admin.merchants.index') }}" class="small"><i class="bi bi-arrow-left"></i> Merchants</a><h1 class="page-title mt-1">New merchant</h1>
        <div class="small text-muted-2">Merchant accounts are created by the platform team. Set a password for the owner, or leave it blank and they get an email to set their own.</div></div>
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

                <div class="col-12"><div class="small fw-semibold text-muted-2 text-uppercase">Password <span class="text-lowercase fw-normal">(optional)</span></div>
                    <div class="form-text mt-0">Leave blank to email the owner a link to set their own password.</div></div>
                <div class="col-md-6"><label class="form-label" for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">At least {{ app()->isProduction() ? '12 characters with upper and lower case, a number and a symbol' : '10 characters with upper and lower case and a number' }}.</div></div>
                <div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-control"></div>
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
                <li class="mb-1"><strong>Password entered:</strong> the owner logs in with it. Share it with them privately.</li>
                <li class="mb-1"><strong>Password blank:</strong> the owner is emailed a link to set their own password (valid {{ config('auth.passwords.users.expire', 60) }} minutes; after that, "Forgot password" on the login page). If mail isn't set up on the server, the link is shown to you instead.</li>
                <li class="mb-1">They can use test mode straight away.</li>
                <li>For live payments they submit business verification, which you approve under Verifications.</li>
            </ol>
        </div></div>
    </div>
</div>
@endsection

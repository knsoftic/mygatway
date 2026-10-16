@extends('layouts.app', ['title' => 'Settings', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">System settings</h1><div class="small text-muted-2">Platform-wide behaviour. Changes are audit-logged.</div></div>
    @can('providers.manage')
        <a class="btn btn-outline-primary" href="{{ route('admin.providers.index') }}"><i class="bi bi-key"></i> Provider API keys</a>
    @endcan
@endsection

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="row g-4" data-confirm="Save system settings?">
    @csrf @method('PUT')
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header">General</div><div class="card-body">
            <label class="form-label" for="support_email">Platform support email</label>
            <input id="support_email" type="email" name="platform_support_email" required value="{{ old('platform_support_email', $settings['platform.support_email']) }}" class="form-control @error('platform_support_email') is-invalid @enderror">
            @error('platform_support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Shown to merchants in emails and on the contact page.</div>

            <hr>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="merchants_auto_activate_on_verification" value="1" id="auto_activate" @checked($settings['merchants.auto_activate_on_verification'])>
                <label class="form-check-label" for="auto_activate">Activate pending merchants when their verification is approved</label>
            </div>
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header">Payouts</div><div class="card-body">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="payouts_manual_approval" value="1" id="manual_approval" @checked($settings['payouts.manual_approval'])>
                <label class="form-check-label" for="manual_approval">Require admin approval for every live payout</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="payouts_pass_network_fee_to_merchant" value="1" id="pass_fee" @checked($settings['payouts.pass_network_fee_to_merchant'])>
                <label class="form-check-label" for="pass_fee">Add the provider's network fee to the merchant's total debit</label>
            </div>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card border-danger-subtle"><div class="card-header text-danger">Maintenance</div><div class="card-body">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="maintenance_block_new_payments" value="1" id="block_payments" @checked($settings['maintenance.block_new_payments'])>
                <label class="form-check-label" for="block_payments">Block new payments <span class="text-muted-2">(API returns a maintenance error; existing payments keep processing)</span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="maintenance_block_new_payouts" value="1" id="block_payouts" @checked($settings['maintenance.block_new_payouts'])>
                <label class="form-check-label" for="block_payouts">Block new payout requests</label>
            </div>
        </div></div>
    </div>
    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary" data-loading><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="btn-label">Save settings</span></button>
    </div>
</form>
@endsection

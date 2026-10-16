@extends('layouts.app', ['title' => 'Notification preferences'])

@section('header')
    <h1 class="page-title">Notification preferences</h1>
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.notifications.update') }}" class="card" style="max-width:40rem">
        @csrf @method('PUT')
        <div class="card-body">
            @foreach ([
                'payment_completed' => ['Payment received', 'Email and dashboard alert when a payment completes or is underpaid.'],
                'payout_status' => ['Payout updates', 'When a payout is completed, failed, rejected or cancelled.'],
                'weekly_summary' => ['Weekly summary', 'A weekly digest of volume and fees.'],
                'security_alerts' => ['Security alerts', 'Logins from new devices and key changes. Always on.'],
            ] as $key => [$label, $text])
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="prefs[]" value="{{ $key }}" id="p-{{ $key }}" @checked($prefs[$key] ?? false) @disabled($key === 'security_alerts')>
                    <label class="form-check-label" for="p-{{ $key }}"><span class="fw-semibold">{{ $label }}</span><div class="small text-muted-2">{{ $text }}</div></label>
                </div>
            @endforeach
        </div>
        <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">Save preferences</button></div>
    </form>
@endsection

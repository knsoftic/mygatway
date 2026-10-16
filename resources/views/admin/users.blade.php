@extends('layouts.app', ['title' => 'Admin users', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Admin users</h1><div class="small text-muted-2">Platform staff and their roles. Every admin must enable two-factor authentication before the panel opens.</div></div>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            @if ($users->isEmpty())
                <x-empty-state icon="people" title="No admin users" />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($users as $u)
                        <li class="list-group-item bg-transparent">
                            <form method="POST" action="{{ route('admin.users.update', $u) }}" class="row g-2 align-items-center" data-confirm="Update {{ $u->email }}?">
                                @csrf @method('PUT')
                                <div class="col-md-4">
                                    <div class="fw-semibold">{{ $u->name }} @if ($u->is(auth()->user()))<span class="badge badge-soft text-bg-info">You</span>@endif</div>
                                    <div class="small text-muted-2">{{ $u->email }}</div>
                                    <div class="small">@if ($u->two_factor_confirmed_at)<span class="text-success"><i class="bi bi-shield-check"></i> 2FA on</span>@else<span class="text-warning"><i class="bi bi-shield-exclamation"></i> 2FA not set up</span>@endif
                                        <span class="text-muted-2">· last login {{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->diffForHumans() : 'never' }}</span></div>
                                </div>
                                <div class="col-md-5 d-flex flex-wrap gap-2">
                                    @foreach ($roles as $role)
                                        <div class="form-check form-check-inline me-1">
                                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="r{{ $u->id }}-{{ $role->id }}" @checked($u->roles->contains($role))>
                                            <label class="form-check-label small" for="r{{ $u->id }}-{{ $role->id }}">{{ $role->label ?? $role->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="Status for {{ $u->email }}"><option value="active" @selected(($u->status ?? 'active') === 'active')>Active</option><option value="disabled" @selected($u->status === 'disabled')>Disabled</option></select></div>
                                <div class="col-md-1 text-end"><button class="btn btn-sm btn-outline-primary">Save</button></div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-4"><div class="card-header">Grant admin access</div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="card-body" data-confirm="Grant admin access to this person?">
                @csrf
                <div class="mb-2"><label class="form-label small mb-1" for="uname">Name</label><input id="uname" name="name" required maxlength="255" value="{{ old('name') }}" class="form-control form-control-sm @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-2"><label class="form-label small mb-1" for="uemail">Email</label><input id="uemail" type="email" name="email" required maxlength="255" value="{{ old('email') }}" class="form-control form-control-sm @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-3"><div class="form-label small mb-1">Roles</div>
                    @foreach ($roles as $role)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="nr{{ $role->id }}" @checked(in_array($role->id, old('roles', [])))><label class="form-check-label small" for="nr{{ $role->id }}">{{ $role->label ?? $role->name }}</label></div>
                    @endforeach
                    @error('roles')<div class="small text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="form-text mb-2">New accounts get a password setup link by email. Existing users keep their password.</div>
                <button class="btn btn-sm btn-primary">Grant access</button>
            </form>
        </div>
        <div class="card"><div class="card-header">Roles</div>
            <ul class="list-group list-group-flush">
                @foreach ($roles as $role)
                    <li class="list-group-item bg-transparent small">
                        <div class="fw-semibold">{{ $role->label ?? $role->name }} <span class="font-mono text-muted-2">{{ $role->name }}</span></div>
                        @if ($role->description)<div class="text-muted-2">{{ $role->description }}</div>@endif
                        <details class="mt-1"><summary class="text-muted-2">{{ $role->permissions->count() }} permission(s)</summary>
                            <div class="d-flex flex-wrap gap-1 mt-1">@foreach ($role->permissions as $perm)<span class="badge badge-soft text-bg-secondary">{{ $perm->name }}</span>@endforeach</div></details>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

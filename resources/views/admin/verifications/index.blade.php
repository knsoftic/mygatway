@extends('layouts.app', ['title' => 'Verifications', 'area' => 'admin'])

@section('header')
    <div><h1 class="page-title">Merchant verifications</h1><div class="small text-muted-2">Review business details before enabling live transactions</div></div>
@endsection

@section('content')
    <ul class="nav nav-pills mb-3">
        @foreach (['submitted' => 'Awaiting review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <li class="nav-item"><a class="nav-link @if ($status === $key) active @endif" href="{{ route('admin.verifications.index', ['status' => $key]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <div class="card">
        @if ($verifications->isEmpty())
            <x-empty-state icon="patch-check" title="{{ $status === 'submitted' ? 'Nothing waiting for review' : 'No '.$status.' verifications' }}" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>#</th><th>Merchant</th><th>Submitted by</th><th>Submitted</th><th>Status</th>@if ($status !== 'submitted')<th>Reviewed by</th>@endif<th></th></tr></thead>
                <tbody>
                @foreach ($verifications as $v)
                    <tr>
                        <td class="font-mono small">{{ $v->id }}</td>
                        <td><span class="fw-semibold">{{ $v->merchant->name }}</span><div class="small text-muted-2 font-mono">{{ $v->merchant->public_id }}</div></td>
                        <td class="small">{{ $v->submitter?->name ?? '—' }}<div class="text-muted-2">{{ $v->submitter?->email }}</div></td>
                        <td class="small text-muted-2 text-nowrap">{{ $v->created_at->format('M j, Y H:i') }}<div>{{ $v->created_at->diffForHumans() }}</div></td>
                        <td><x-status-badge :status="\App\Enums\VerificationStatus::tryFrom($v->status) ?? $v->status" /></td>
                        @if ($status !== 'submitted')<td class="small">{{ $v->reviewer?->name ?? '—' }}<div class="text-muted-2">{{ $v->reviewed_at?->format('M j, Y') }}</div></td>@endif
                        <td class="text-end"><a href="{{ route('admin.verifications.show', $v) }}" class="btn btn-sm btn-outline-primary">{{ $status === 'submitted' ? 'Review' : 'View' }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body border-top">{{ $verifications->links() }}</div>
        @endif
    </div>
@endsection

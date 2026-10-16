@extends('layouts.app', ['title' => 'Notifications', 'area' => $area])

@section('header')
    <h1 class="page-title">Notifications</h1>
@endsection

@section('content')
    <div class="card" style="max-width:48rem">
        @if ($notifications->isEmpty())
            <x-empty-state icon="bell" title="You're all caught up" />
        @else
            <ul class="list-group list-group-flush">
                @foreach ($notifications as $n)
                    <li class="list-group-item bg-transparent d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">{{ $n->data['title'] ?? 'Notification' }} @if (! $n->read_at)<span class="badge text-bg-primary ms-1">New</span>@endif</div>
                            <div class="small text-muted-2">{{ $n->data['message'] ?? '' }}</div>
                        </div>
                        <div class="text-end small text-nowrap">
                            <div class="text-muted-2">{{ $n->created_at->diffForHumans() }}</div>
                            @if (! empty($n->data['url']) && str_starts_with($n->data['url'], url('/')))<a href="{{ $n->data['url'] }}">View</a>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="card-body border-top">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection

@extends('layouts.public', ['title' => $title.' · Docs'])

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row g-4">
        <aside class="col-lg-3">
            <div class="position-sticky" style="top:5.5rem">
                <button class="btn btn-outline-secondary btn-sm w-100 d-lg-none mb-2" data-bs-toggle="collapse" data-bs-target="#docsNav" aria-expanded="false" aria-controls="docsNav"><i class="bi bi-list"></i> Documentation menu</button>
                <nav id="docsNav" class="collapse d-lg-block" aria-label="Documentation">
                    @foreach ($sections as $section => $pages)
                        <div class="eyebrow mt-3 mb-2">{{ $section }}</div>
                        <ul class="nav flex-column small">
                            @foreach ($pages as $slug => $label)
                                <li class="nav-item"><a class="nav-link px-2 py-1 rounded {{ $slug === $page ? 'bg-brand-soft text-brand fw-semibold' : 'text-body' }}" href="{{ route('docs.show', $slug) }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    @endforeach
                </nav>
            </div>
        </aside>
        <article class="col-lg-9">
            <div class="card"><div class="card-body p-4 p-lg-5 prose docs">
                <h1 class="h2 fw-bold ls-tight mb-3">{{ $title }}</h1>
                @include('docs.pages.'.$page)
            </div></div>
            <div class="d-flex justify-content-between mt-3">
                @if ($prev)<a href="{{ route('docs.show', $prev[0]) }}" class="btn btn-link"><i class="bi bi-arrow-left"></i> {{ $prev[1] }}</a>@else<span></span>@endif
                @if ($next)<a href="{{ route('docs.show', $next[0]) }}" class="btn btn-link">{{ $next[1] }} <i class="bi bi-arrow-right"></i></a>@endif
            </div>
        </article>
    </div>
</div>
@endsection

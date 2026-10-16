@extends('layouts.public', ['title' => $heading])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'Legal', 'heading' => $heading, 'intro' => 'Last updated: '.$updated])

<section class="section pt-4">
    <div class="container" style="max-width:48rem">
        <div class="alert alert-warning small"><i class="bi bi-info-circle me-1"></i>This is a template. Have it reviewed by a qualified lawyer for your jurisdiction and your providers' terms before going live.</div>
        <div class="card"><div class="card-body p-4 p-lg-5 prose">
            @yield('legal')
        </div></div>
    </div>
</section>
@endsection

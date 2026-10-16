<section class="hero py-5">
    <div class="container py-lg-4 text-center fade-up">
        @isset($eyebrow)<div class="eyebrow mb-2">{{ $eyebrow }}</div>@endisset
        <h1 class="section-title display-5 mb-3">{{ $heading }}</h1>
        @isset($intro)<p class="lead text-muted-2 mx-auto mb-0" style="max-width:44rem">{{ $intro }}</p>@endisset
    </div>
</section>

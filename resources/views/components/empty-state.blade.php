@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <i class="bi bi-{{ $icon }}"></i>
    <div class="fw-semibold text-body">{{ $title }}</div>
    @if ($text)
        <div class="small mt-1">{{ $text }}</div>
    @endif
    {{ $slot }}
</div>

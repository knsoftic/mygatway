@props(['label', 'icon' => 'graph-up', 'hint' => null])
<div {{ $attributes->merge(['class' => 'card stat-card h-100']) }}>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="stat-label">{{ $label }}</div>
            <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
        </div>
        <div class="stat-value">{{ $slot }}</div>
        @if ($hint)
            <div class="small text-muted-2 mt-1">{{ $hint }}</div>
        @endif
    </div>
</div>

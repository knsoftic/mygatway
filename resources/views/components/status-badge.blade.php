@props(['status'])
@php
    $color = is_object($status) && method_exists($status, 'badge') ? $status->badge() : 'secondary';
    $label = is_object($status) && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', (string) (is_object($status) ? $status->value : $status)));
@endphp
<span {{ $attributes->merge(['class' => "badge badge-soft text-bg-{$color}"]) }}>{{ $label }}</span>

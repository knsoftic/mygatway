@props(['amount', 'currency' => null, 'decimals' => 2])
<span {{ $attributes->merge(['class' => 'amount']) }}>{{ \App\Support\Decimal::format($amount === null ? null : (string) $amount, $decimals) }}@if ($currency && $amount !== null) <span class="text-muted-2 small">{{ $currency }}</span>@endif</span>

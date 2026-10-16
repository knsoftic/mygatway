@extends('layouts.public', ['title' => 'Supported payment methods'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'Payment methods', 'heading' => 'Supported providers and currencies', 'intro' => 'Only providers that are integrated and enabled are listed. Currencies come from the live provider configuration.'])

<section class="section pt-4">
    <div class="container">
        @forelse ($providers as $provider)
            @php $caps = $gateways->live($provider->code)->capabilities(); @endphp
            <div class="card mb-4"><div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h4 fw-bold mb-1">{{ $provider->name }}</h2>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($provider->payin_enabled)<span class="badge badge-soft text-bg-success">Pay In</span>@endif
                            @if ($provider->payout_enabled && $caps->payOut)<span class="badge badge-soft text-bg-info">Pay Out</span>@endif
                            @if ($provider->refund_enabled && $caps->refund)<span class="badge badge-soft text-bg-secondary">Refunds</span>@endif
                        </div>
                    </div>
                    @if ($caps->payOut && $provider->payout_enabled)
                        <div class="small text-muted-2 text-md-end">
                            Payout destinations:<br>
                            <strong class="text-body">{{ collect($caps->payoutDestinationTypes)->map->label()->join(', ') }}</strong>
                        </div>
                    @endif
                </div>
                <ul class="small text-muted-2 mb-3">
                    @foreach ($caps->notes as $note)<li>{{ $note }}</li>@endforeach
                </ul>
                @foreach (['payin' => 'Pay In currencies', 'payout' => 'Payout currencies'] as $dir => $label)
                    @php $rows = $provider->currencies->where('direction', $dir); @endphp
                    @if ($rows->isNotEmpty())
                        <div class="fw-semibold small mb-2">{{ $label }}</div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($rows->groupBy('currency') as $currency => $items)
                                <span class="badge rounded-pill text-bg-light border fw-semibold">{{ $currency }}
                                    @if ($items->pluck('network')->filter()->isNotEmpty())
                                        <span class="text-muted-2 fw-normal">· {{ $items->pluck('network')->filter()->map(fn ($n) => strtoupper($n))->join(', ') }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div></div>
        @empty
            <x-empty-state icon="plug" title="No providers are enabled yet" text="Providers appear here once an administrator has configured and enabled them." />
        @endforelse
        <p class="small text-muted-2">Availability, limits and networks can change based on the provider. The API endpoint <code>GET /api/v1/currencies</code> always returns the current list.</p>
    </div>
</section>
@endsection

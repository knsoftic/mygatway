@extends('layouts.public', ['title' => 'Pricing'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'Pricing', 'heading' => 'Transparent pricing', 'intro' => 'No setup fees and no monthly fees. You pay a service fee only on successful payments and completed payouts.'])

<section class="section pt-4">
    <div class="container" style="max-width:60rem">
        <div class="row g-4 mb-5">
            @foreach (['payin' => ['Pay In', 'Charged on each successful payment, deducted from the received amount.'], 'payout' => ['Pay Out', 'Charged on each completed payout, added to the amount debited from your balance.']] as $key => [$label, $text])
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-body p-4 p-lg-5 text-center">
                        <div class="eyebrow">{{ $label }}</div>
                        <div class="display-4 fw-bold ls-tight my-2">{{ $pricing[$key]?->describe() ?? 'Custom' }}</div>
                        <p class="text-muted-2 small">{{ $text }}</p>
                        @if ($pricing[$key]?->min_fee || $pricing[$key]?->max_fee)
                            <p class="small mb-0">Min {{ $pricing[$key]->min_fee ?? '—' }} / Max {{ $pricing[$key]->max_fee ?? '—' }}</p>
                        @endif
                    </div></div>
                </div>
            @endforeach
        </div>

        @if ($providerRules->isNotEmpty())
            <div class="card mb-5"><div class="card-header">Provider-specific rates</div>
                <div class="table-responsive"><table class="table">
                    <thead><tr><th>Direction</th><th>Provider</th><th>Currency</th><th>Fee</th></tr></thead>
                    <tbody>
                    @foreach ($providerRules as $rule)
                        <tr><td>{{ $rule->direction->label() }}</td><td>{{ $rule->provider?->name }}</td><td>{{ $rule->currency ?? 'All' }}</td><td>{{ $rule->describe() }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif

        <h2 class="h4 fw-bold mb-3">Worked examples</h2>
        <div class="row g-4 mb-4">
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h3 class="h6 fw-bold">Pay In of 10,000 USDT at 1%</h3>
                <table class="table table-sm mb-0">
                    <tr><td>Gross amount received</td><td class="text-end amount">10,000.00</td></tr>
                    <tr><td>Service fee (1%)</td><td class="text-end amount">− 100.00</td></tr>
                    <tr class="fw-bold"><td>Credited to your balance</td><td class="text-end amount">9,900.00</td></tr>
                </table>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-body p-4">
                <h3 class="h6 fw-bold">Payout of 10,000 USDT at 1%</h3>
                <table class="table table-sm mb-0">
                    <tr><td>Amount sent to recipient</td><td class="text-end amount">10,000.00</td></tr>
                    <tr><td>Service fee (1%)</td><td class="text-end amount">+ 100.00</td></tr>
                    <tr><td>Network fee (provider estimate)</td><td class="text-end amount">+ N</td></tr>
                    <tr class="fw-bold"><td>Total debited from balance</td><td class="text-end amount">10,100.00 + N</td></tr>
                </table>
            </div></div></div>
        </div>
        <p class="small text-muted-2">Fees are always calculated by the platform at the time of the transaction and stored with it; changing a fee rule never alters past transactions. Underpaid or overpaid payments are charged on the amount actually received. Provider fees charged to us are not passed on for Pay In. Custom pricing for high volumes is available on request.</p>
    </div>
</section>
@endsection

<?php

namespace App\Http\Controllers\Web;

use App\Enums\FeeDirection;
use App\Http\Controllers\Controller;
use App\Integrations\GatewayManager;
use App\Models\FeeRule;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Public marketing and legal pages. Provider, currency and pricing data shown here
 * comes from the live configuration (enabled providers, active global fee rules).
 */
class PageController extends Controller
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function home(): View
    {
        return view('pages.home', [
            'providers' => $this->enabledProviders(),
            'pricing' => $this->globalPricing(),
        ]);
    }

    public function pricing(): View
    {
        return view('pages.pricing', [
            'pricing' => $this->globalPricing(),
            'providerRules' => FeeRule::query()->with('provider')->effectiveAt(now())
                ->whereNull('merchant_id')->whereNotNull('provider_id')->orderBy('direction')->get(),
        ]);
    }

    public function paymentMethods(): View
    {
        $providers = $this->enabledProviders()->map(function (PaymentProvider $p) {
            $p->setRelation('currencies', SupportedCurrency::query()->where('provider_id', $p->id)->usable()
                ->orderBy('currency')->orderBy('network')->get()
                ->filter(fn ($c) => $c->direction === 'payin' ? $p->payin_enabled : $p->payout_enabled));

            return $p;
        });

        return view('pages.payment-methods', ['providers' => $providers, 'gateways' => $this->gateways]);
    }

    public function show(string $page): View
    {
        return view('pages.'.$page);
    }

    /** @return Collection<int, PaymentProvider> */
    private function enabledProviders(): Collection
    {
        return PaymentProvider::query()
            ->where(fn ($q) => $q->where('payin_enabled', true)->orWhere('payout_enabled', true))
            ->orderBy('priority')->get()
            ->filter(fn (PaymentProvider $p) => $this->gateways->has($p->code))
            ->values();
    }

    /** @return array{payin: ?FeeRule, payout: ?FeeRule} */
    private function globalPricing(): array
    {
        $rule = fn (FeeDirection $d) => FeeRule::query()->effectiveAt(now())
            ->where('direction', $d->value)->whereNull('merchant_id')->whereNull('provider_id')->whereNull('currency')
            ->orderByDesc('effective_from')->first();

        return ['payin' => $rule(FeeDirection::PayIn), 'payout' => $rule(FeeDirection::PayOut)];
    }
}

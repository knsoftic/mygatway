<?php

namespace App\Services\Providers;

use App\Enums\FeeDirection;
use App\Enums\Mode;
use App\Enums\PayoutDestinationType;
use App\Exceptions\GatewayException;
use App\Integrations\Cryptomus\CryptomusGateway;
use App\Integrations\GatewayManager;
use App\Models\PaymentProvider;
use App\Models\SupportedCurrency;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Chooses the provider and currency/network configuration for a request, using only
 * providers an admin has enabled, whose credentials are configured (live mode), that
 * support the operation, and that list the currency as available.
 */
class ProviderRouter
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /** @return array{0: PaymentProvider, 1: SupportedCurrency} */
    public function forPayIn(?string $providerCode, string $currency, ?string $network, string $amount, Mode $mode): array
    {
        return $this->resolve(FeeDirection::PayIn, $providerCode, $currency, $network, $amount, $mode, null);
    }

    /** @return array{0: PaymentProvider, 1: SupportedCurrency} */
    public function forPayOut(?string $providerCode, string $currency, ?string $network, string $amount, Mode $mode, PayoutDestinationType $destination): array
    {
        return $this->resolve(FeeDirection::PayOut, $providerCode, $currency, $network, $amount, $mode, $destination);
    }

    /** @return array{0: PaymentProvider, 1: SupportedCurrency} */
    private function resolve(FeeDirection $direction, ?string $code, string $currency, ?string $network, string $amount, Mode $mode, ?PayoutDestinationType $destination): array
    {
        $currency = strtoupper($currency);
        $candidates = PaymentProvider::query()
            ->when($code, fn ($q) => $q->where('code', $code))
            ->where($direction === FeeDirection::PayIn ? 'payin_enabled' : 'payout_enabled', true)
            ->orderBy('priority')
            ->get()
            ->filter(fn (PaymentProvider $p) => $this->gateways->has($p->code));

        if ($candidates->isEmpty()) {
            throw new GatewayException('provider_not_available',
                $code ? "Provider [{$code}] is not available for ".$direction->label().'.' : 'No provider is currently available for '.$direction->label().'.');
        }

        $errors = [];
        foreach ($candidates as $provider) {
            try {
                return [$provider, $this->check($provider, $direction, $currency, $network, $amount, $mode, $destination)];
            } catch (GatewayException $e) {
                if ($code) {
                    throw $e;
                }
                $errors[$provider->code] = $e;
            }
        }

        // Auto-routing: report why each enabled provider was skipped.
        $first = reset($errors);
        $allSame = count(array_unique(array_map(fn (GatewayException $e) => $e->errorCode, $errors))) === 1;

        throw new GatewayException(
            $allSame ? $first->errorCode : 'provider_not_available',
            $allSame && $first->errorCode === 'currency_not_supported'
                ? "{$currency} is not supported by any enabled provider for ".$direction->label().'.'
                : 'No enabled provider can process this request.',
            422,
            ['providers' => array_map(fn (GatewayException $e) => ['code' => $e->errorCode, 'message' => $e->getMessage()], $errors)],
        );
    }

    private function check(PaymentProvider $provider, FeeDirection $direction, string $currency, ?string $network, string $amount, Mode $mode, ?PayoutDestinationType $destination): SupportedCurrency
    {
        $gateway = $this->gateways->live($provider->code);
        $caps = $gateway->capabilities();

        if ($direction === FeeDirection::PayIn && ! $caps->payIn) {
            throw GatewayException::unsupported("{$provider->name} does not support Pay In.");
        }

        if ($direction === FeeDirection::PayOut) {
            if (! $caps->payOut) {
                throw GatewayException::unsupported("{$provider->name} does not support payouts.");
            }
            if ($destination && ! $caps->supportsDestination($destination)) {
                throw new GatewayException('destination_not_supported',
                    "{$provider->name} cannot pay out to a {$destination->label()}.", 422, [
                        'provider' => $provider->code,
                        'supported_destination_types' => array_map(fn ($t) => $t->value, $caps->payoutDestinationTypes),
                    ]);
            }
        }

        if ($mode === Mode::Live) {
            $configured = $direction === FeeDirection::PayOut && $gateway instanceof CryptomusGateway
                ? $gateway->payoutsConfigured()
                : $gateway->isConfigured();
            if (! $configured) {
                throw new GatewayException('provider_not_available', "{$provider->name} is not configured for live ".$direction->label().'.');
            }
        }

        /** @var Collection<int, SupportedCurrency> $rows */
        $rows = SupportedCurrency::query()
            ->where('provider_id', $provider->id)
            ->where('direction', $direction->value)
            ->where('currency', $currency)
            ->usable()
            ->get();

        if ($rows->isEmpty()) {
            throw new GatewayException('currency_not_supported', "{$currency} is not supported by {$provider->name} for ".$direction->label().'.', 422, [
                'provider' => $provider->code,
                'currency' => $currency,
            ]);
        }

        $networks = $rows->pluck('network')->filter(fn ($n) => $n !== '')->values();

        if ($network !== null && $network !== '') {
            $row = $rows->first(fn (SupportedCurrency $r) => strcasecmp($r->network, $network) === 0);
            if (! $row) {
                throw new GatewayException('network_not_supported', "Network [{$network}] is not supported for {$currency} on {$provider->name}.", 422, [
                    'supported_networks' => $networks->all(),
                ]);
            }
        } elseif ($direction === FeeDirection::PayOut && $caps->payoutRequiresNetwork && $networks->count() > 1) {
            throw new GatewayException('network_required', "A network is required for {$currency} payouts on {$provider->name}.", 422, [
                'supported_networks' => $networks->all(),
            ]);
        } else {
            $row = $rows->firstWhere('network', '') ?? $rows->first();
        }

        if ($row->min_amount !== null && Decimal::lt($amount, (string) $row->min_amount)) {
            throw new GatewayException('amount_out_of_range', "Minimum amount is {$row->min_amount} {$currency}.", 422, ['min_amount' => $row->min_amount]);
        }
        if ($row->max_amount !== null && Decimal::gt($amount, (string) $row->max_amount)) {
            throw new GatewayException('amount_out_of_range', "Maximum amount is {$row->max_amount} {$currency}.", 422, ['max_amount' => $row->max_amount]);
        }

        return $row;
    }
}

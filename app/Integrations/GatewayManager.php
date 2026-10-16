<?php

namespace App\Integrations;

use App\Enums\Mode;
use App\Integrations\BinancePay\BinancePayGateway;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Cryptomus\CryptomusGateway;
use App\Integrations\Sandbox\SandboxGateway;
use App\Models\PaymentProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the adapter for a provider and mode. Live mode always talks to the
 * real provider; test mode is always served by the sandbox simulator.
 */
class GatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const ADAPTERS = [
        PaymentProvider::CRYPTOMUS => CryptomusGateway::class,
        PaymentProvider::BINANCE_PAY => BinancePayGateway::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(PaymentProvider|string $provider, Mode $mode): PaymentGateway
    {
        $live = $this->live($provider instanceof PaymentProvider ? $provider->code : $provider);

        return $mode === Mode::Test ? new SandboxGateway($live) : $live;
    }

    public function live(string $code): PaymentGateway
    {
        $class = self::ADAPTERS[$code] ?? throw new InvalidArgumentException("Unknown payment provider [{$code}].");

        return $this->container->make($class);
    }

    public function sandbox(string $code): SandboxGateway
    {
        return new SandboxGateway($this->live($code));
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys(self::ADAPTERS);
    }

    public function has(string $code): bool
    {
        return isset(self::ADAPTERS[$code]);
    }
}

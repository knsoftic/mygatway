<?php

namespace App\Integrations\BinancePay;

use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\ProviderNotConfiguredException;
use App\Integrations\Support\JsonBody;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Low-level client for https://bpay.binanceapi.com (Binance Pay Merchant API).
 */
class BinancePayClient
{
    /** Result codes that mean "temporary, retry later". */
    private const RETRYABLE_CODES = ['400000', '400003', '400501'];

    /** @param  array<string, mixed>  $config  config('services.binance_pay') */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null) && filled($this->config['secret_key'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return mixed The "data" member of a SUCCESS response.
     *
     * @throws ProviderException
     */
    public function post(string $path, array $payload): mixed
    {
        if (! $this->isConfigured()) {
            throw new ProviderNotConfiguredException('binance_pay', 'BINANCE_PAY_API_KEY / BINANCE_PAY_SECRET_KEY');
        }

        $body = $payload === [] ? '{}' : JsonBody::encode($payload);
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $nonce = BinancePaySignature::nonce();

        try {
            $response = $this->http
                ->withHeaders([
                    'BinancePay-Timestamp' => $timestamp,
                    'BinancePay-Nonce' => $nonce,
                    'BinancePay-Certificate-SN' => $this->config['api_key'],
                    'BinancePay-Signature' => BinancePaySignature::signRequest($timestamp, $nonce, $body, $this->config['secret_key']),
                    'Accept' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->connectTimeout(10)
                ->post(rtrim($this->config['base_url'], '/').'/'.ltrim($path, '/'));
        } catch (ConnectionException $e) {
            throw new ProviderException('Binance Pay connection failed: '.$e->getMessage(), 'binance_pay', 'provider_unavailable',
                'The payment provider is temporarily unreachable. Please retry.', true, previous: $e);
        }

        $json = $response->json();

        if ($response->successful() && is_array($json) && ($json['status'] ?? null) === 'SUCCESS') {
            return $json['data'] ?? null;
        }

        $code = is_array($json) ? (string) ($json['code'] ?? '') : '';
        $message = is_array($json) ? (string) ($json['errorMessage'] ?? '') : '';

        Log::warning('Binance Pay API error', ['path' => $path, 'http_status' => $response->status(), 'code' => $code, 'message' => $message]);

        throw $this->mapError($path, $response->status(), $code, $message);
    }

    /**
     * Webhook verification public keys, keyed by certSerial.
     *
     * @return array<string, string>
     */
    public function certificates(bool $refresh = false): array
    {
        $cacheKey = 'binance_pay:certificates';
        if ($refresh) {
            $this->cache->forget($cacheKey);
        }

        return $this->cache->remember($cacheKey, (int) ($this->config['certificate_cache_ttl'] ?? 3600), function () {
            $data = $this->post('binancepay/openapi/certificates', []);
            $rows = isset($data['certSerial']) ? [$data] : (array) $data;
            $certs = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['certSerial'], $row['certPublic'])) {
                    $certs[(string) $row['certSerial']] = (string) $row['certPublic'];
                }
            }

            return $certs;
        });
    }

    private function mapError(string $path, int $status, string $code, string $message): ProviderException
    {
        $context = ['path' => $path, 'code' => $code, 'message' => $message];

        if ($status >= 500 || in_array($code, self::RETRYABLE_CODES, true)) {
            return new ProviderException("Binance Pay temporary error {$code} on {$path}: {$message}", 'binance_pay', 'provider_unavailable',
                'The payment provider is temporarily unavailable. Please retry.', true, $status, $context);
        }

        return match ($code) {
            // 400002 invalid signature, 400004 key/IP/permission rejected: an admin-side setup issue.
            '400002', '400004' => new ProviderException("Binance Pay rejected the credentials ({$code}): {$message}", 'binance_pay', 'provider_auth_failed',
                'The payment provider is not available right now. Please try another provider or contact support.', false, $status, $context),
            '400105', '406104' => new ProviderException("Binance Pay currency not supported: {$message}", 'binance_pay', 'currency_not_supported',
                'This currency is not supported by the provider.', false, $status, $context),
            '400701', '406102' => new ProviderException("Binance Pay amount invalid: {$message}", 'binance_pay', 'amount_out_of_range',
                'The amount is outside the limits accepted by the provider.', false, $status, $context),
            '400703', '406103', '406106', '400706' => new ProviderException("Binance Pay payee invalid: {$message}", 'binance_pay', 'invalid_destination',
                'The Binance recipient could not be found or cannot receive funds.', false, $status, $context),
            '400611' => new ProviderException('Binance Pay insufficient balance', 'binance_pay', 'provider_insufficient_funds',
                'The request could not be funded by the provider. Our team has been notified.', false, $status, $context),
            '400201' => new ProviderException("Binance Pay duplicate merchantTradeNo: {$message}", 'binance_pay', 'duplicate_reference',
                'The provider already has an order with this reference.', false, $status, $context),
            '400202' => new ProviderException('Binance Pay order not found', 'binance_pay', 'provider_order_not_found',
                'The provider could not find this order.', false, $status, $context),
            default => new ProviderException("Binance Pay error {$code} on {$path}: {$message}", 'binance_pay', 'provider_error',
                'The payment provider could not process the request.', false, $status, $context),
        };
    }
}

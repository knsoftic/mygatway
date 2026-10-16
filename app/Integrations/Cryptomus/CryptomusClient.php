<?php

namespace App\Integrations\Cryptomus;

use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\ProviderNotConfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Low-level HTTP client for https://api.cryptomus.com.
 * Every call is a signed POST with a JSON body (merchant + sign headers).
 */
class CryptomusClient
{
    public const KEY_PAYMENT = 'payment';

    public const KEY_PAYOUT = 'payout';

    /** @param  array<string, mixed>  $config  config('services.cryptomus') */
    public function __construct(private readonly HttpFactory $http, private readonly array $config) {}

    public function hasPaymentCredentials(): bool
    {
        return filled($this->config['merchant_id'] ?? null) && filled($this->config['payment_key'] ?? null);
    }

    public function hasPayoutCredentials(): bool
    {
        return filled($this->config['merchant_id'] ?? null) && filled($this->config['payout_key'] ?? null);
    }

    public function paymentKey(): ?string
    {
        return $this->config['payment_key'] ?? null;
    }

    public function payoutKey(): ?string
    {
        return $this->config['payout_key'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|list<mixed> The "result" member of a successful response.
     *
     * @throws ProviderException
     */
    public function post(string $path, array $payload, string $keyType = self::KEY_PAYMENT, array $query = []): array
    {
        $merchantId = $this->config['merchant_id'] ?? null;
        $key = $keyType === self::KEY_PAYOUT ? $this->payoutKey() : $this->paymentKey();

        if (blank($merchantId) || blank($key)) {
            throw new ProviderNotConfiguredException('cryptomus', $keyType === self::KEY_PAYOUT ? 'CRYPTOMUS_PAYOUT_KEY' : 'CRYPTOMUS_PAYMENT_KEY');
        }

        // Sign exactly the bytes we send. Requests without parameters send and sign an
        // empty body, as documented: md5(base64_encode('') . API_KEY).
        $body = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = rtrim($this->config['base_url'], '/').'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            $response = $this->http
                ->withHeaders([
                    'merchant' => $merchantId,
                    'sign' => CryptomusSignature::sign($body, $key),
                    'Accept' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->connectTimeout(10)
                ->post($url);
        } catch (ConnectionException $e) {
            throw new ProviderException(
                'Cryptomus connection failed: '.$e->getMessage(),
                provider: 'cryptomus',
                errorCode: 'provider_unavailable',
                safeMessage: 'The payment provider is temporarily unreachable. Please retry.',
                retryable: true,
                previous: $e,
            );
        }

        $json = $response->json();

        if ($response->successful() && is_array($json) && (int) ($json['state'] ?? 1) === 0) {
            return $json['result'] ?? [];
        }

        $message = is_array($json) ? (string) ($json['message'] ?? '') : '';
        $errors = is_array($json) ? ($json['errors'] ?? null) : null;

        Log::warning('Cryptomus API error', [
            'path' => $path,
            'http_status' => $response->status(),
            'message' => $message,
            'errors' => $errors,
        ]);

        throw $this->mapError($path, $response->status(), $message, $errors);
    }

    private function mapError(string $path, int $status, string $message, mixed $errors): ProviderException
    {
        $context = ['path' => $path, 'message' => $message, 'errors' => $errors];

        if ($status >= 500 || in_array($message, ['Gateway error', 'Server error', 'The terminal was not found'], true) || Str::startsWith($message, 'Server error')) {
            return new ProviderException("Cryptomus temporary error on {$path}: {$message}", 'cryptomus', 'provider_unavailable',
                'The payment provider is temporarily unavailable. Please retry.', true, $status, $context);
        }

        if (Str::contains($message, ['Minimum amount', 'Maximum amount', 'withdrawal amount is too small'])) {
            return new ProviderException("Cryptomus amount out of range: {$message}", 'cryptomus', 'amount_out_of_range',
                $message, false, $status, $context);
        }

        if (Str::contains($message, ['currency was not found', 'network was not found', 'service was not found', 'Not found service', 'Payment service not found', 'Payout service not found'])) {
            return new ProviderException("Cryptomus unsupported currency/network: {$message}", 'cryptomus', 'currency_not_supported',
                'This currency or network is not supported by the provider.', false, $status, $context);
        }

        if ($message === 'Not enough funds') {
            return new ProviderException('Cryptomus: insufficient provider balance', 'cryptomus', 'provider_insufficient_funds',
                'The payout could not be funded by the provider. Our team has been notified.', false, $status, $context);
        }

        if ($errors !== null) {
            return new ProviderException("Cryptomus validation error on {$path}", 'cryptomus', 'provider_validation_error',
                'The payment provider rejected the request parameters.', false, $status, $context);
        }

        return new ProviderException("Cryptomus error on {$path}: {$message}", 'cryptomus', 'provider_error',
            'The payment provider could not process the request.', false, $status, $context);
    }
}

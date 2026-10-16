<?php

namespace App\Integrations\Cryptomus;

use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Data\HealthCheckResult;
use App\Integrations\Data\ProviderCapabilities;
use App\Integrations\Data\ProviderPaymentData;
use App\Integrations\Data\ProviderPayoutData;
use App\Integrations\Data\ProviderRefundData;
use App\Integrations\Data\ProviderWebhookData;
use App\Integrations\Exceptions\InvalidWebhookException;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Cryptomus Merchant API adapter.
 *
 * Endpoints used (all documented at https://doc.cryptomus.com):
 *  POST /v1/payment            create invoice             (payment key)
 *  POST /v1/payment/info       invoice status             (payment key)
 *  POST /v1/payment/refund     refund a paid invoice      (payment key)
 *  POST /v1/payment/services   payment currencies/limits  (payment key)
 *  POST /v1/payout             create payout              (payout key)
 *  POST /v1/payout/info        payout status              (payout key)
 *  POST /v1/payout/services    payout currencies/limits   (payout key)
 */
class CryptomusGateway implements PaymentGateway
{
    public function __construct(private readonly CryptomusClient $client, private readonly array $config) {}

    public function code(): string
    {
        return 'cryptomus';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            payIn: true,
            payOut: true,
            refund: true,
            partialRefund: false,
            webhooks: true,
            statusQuery: true,
            providerSandbox: false,
            payoutDestinationTypes: [PayoutDestinationType::CryptoAddress],
            payoutRequiresNetwork: true,
            notes: [
                'Payouts are sent to blockchain wallet addresses from the Cryptomus business wallet balance.',
                'Refunds return the full paid amount to a customer wallet address (no partial refunds).',
                'Cryptomus has no sandbox; it offers test-webhook endpoints only.',
                'Webhooks are signed with the payment API key and sent from 91.227.144.54.',
            ],
        );
    }

    public function isConfigured(): bool
    {
        return $this->client->hasPaymentCredentials();
    }

    public function payoutsConfigured(): bool
    {
        return $this->client->hasPayoutCredentials();
    }

    public function createPayment(Payment $payment, array $options): ProviderPaymentData
    {
        $allowed = ['currency' => $payment->currency];
        if ($payment->network) {
            $allowed['network'] = $payment->network;
        }

        $payload = array_filter([
            'amount' => Decimal::plain($payment->amount),
            'currency' => $payment->currency,
            'order_id' => $payment->provider_order_id,
            'network' => $payment->network,
            'url_return' => $payment->cancel_url,
            'url_success' => $payment->success_url,
            'url_callback' => $options['callback_url'],
            'is_payment_multiple' => (bool) ($this->config['is_payment_multiple'] ?? false),
            'lifetime' => $options['lifetime'],
            // Lock the payer to the requested asset so the credited currency matches the invoice.
            'currencies' => [$allowed],
            'additional_data' => $payment->public_id,
        ], fn ($v) => $v !== null);

        $result = $this->client->post('v1/payment', $payload);

        return $this->toPaymentData($result);
    }

    public function fetchPayment(Payment $payment): ProviderPaymentData
    {
        $payload = $payment->provider_payment_id
            ? ['uuid' => $payment->provider_payment_id]
            : ['order_id' => $payment->provider_order_id];

        return $this->toPaymentData($this->client->post('v1/payment/info', $payload));
    }

    public function createPayout(Payout $payout, string $callbackUrl): ProviderPayoutData
    {
        if ($payout->destination_type !== PayoutDestinationType::CryptoAddress) {
            throw new UnsupportedOperationException('cryptomus', 'payouts to '.$payout->destination_type->value, 'Cryptomus pays out to blockchain addresses only.');
        }

        $payload = array_filter([
            'amount' => Decimal::plain($payout->amount),
            'currency' => $payout->currency,
            'network' => $payout->network,
            'order_id' => $payout->provider_order_id,
            'address' => $payout->destination,
            // Fee is taken from the platform balance so the recipient receives the exact amount.
            'is_subtract' => true,
            'url_callback' => $callbackUrl,
            'memo' => $payout->destination_memo,
        ], fn ($v) => $v !== null);

        return $this->toPayoutData($this->client->post('v1/payout', $payload, CryptomusClient::KEY_PAYOUT));
    }

    public function fetchPayout(Payout $payout): ProviderPayoutData
    {
        $payload = $payout->provider_payout_id
            ? ['uuid' => $payout->provider_payout_id]
            : ['order_id' => $payout->provider_order_id];

        return $this->toPayoutData($this->client->post('v1/payout/info', $payload, CryptomusClient::KEY_PAYOUT));
    }

    public function refundPayment(Refund $refund, Payment $payment): ProviderRefundData
    {
        if (blank($refund->address)) {
            throw new ProviderException('Cryptomus refund requires a wallet address', 'cryptomus', 'refund_address_required',
                'A customer wallet address is required to refund a Cryptomus payment.');
        }

        $this->client->post('v1/payment/refund', array_filter([
            'uuid' => $payment->provider_payment_id,
            'order_id' => $payment->provider_payment_id ? null : $payment->provider_order_id,
            'address' => $refund->address,
            'is_subtract' => true,
        ], fn ($v) => $v !== null));

        // Completion is reported through the payment webhook (refund_process -> refund_paid).
        return new ProviderRefundData('refund_process', RefundStatus::Processing);
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        if (($this->config['enforce_webhook_ip'] ?? true) && ! IpUtils::checkIp((string) $request->ip(), $this->config['webhook_ips'] ?? [])) {
            throw new InvalidWebhookException('Cryptomus webhook from unexpected IP '.$request->ip(), 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            throw new InvalidWebhookException('Cryptomus webhook body is not valid JSON', 400);
        }

        $type = (string) ($payload['type'] ?? '');
        $keys = $type === 'payout'
            ? [$this->client->paymentKey(), $this->client->payoutKey()]
            : [$this->client->paymentKey()];

        if (! CryptomusSignature::verifyWebhook($payload, array_values(array_filter($keys)))) {
            throw new InvalidWebhookException('Cryptomus webhook signature mismatch');
        }

        $status = (string) ($payload['status'] ?? '');
        $reference = isset($payload['order_id']) ? (string) $payload['order_id'] : null;
        $externalId = isset($payload['uuid']) ? (string) $payload['uuid'] : null;

        return match ($type) {
            'payment' => new ProviderWebhookData(
                kind: ProviderWebhookData::KIND_PAYMENT,
                eventType: 'payment.'.$status,
                reference: $reference,
                externalId: $externalId,
                payment: $this->toPaymentData($payload),
            ),
            'payout' => new ProviderWebhookData(
                kind: ProviderWebhookData::KIND_PAYOUT,
                eventType: 'payout.'.$status,
                reference: $reference,
                externalId: $externalId,
                payout: $this->toPayoutData($payload),
            ),
            // Static-wallet ("wallet") callbacks are not used by this platform.
            default => new ProviderWebhookData(ProviderWebhookData::KIND_UNKNOWN, $type.'.'.$status, $reference, $externalId),
        };
    }

    public function healthCheck(): HealthCheckResult
    {
        if (! $this->isConfigured()) {
            return new HealthCheckResult(HealthCheckResult::UNCONFIGURED, 'Merchant UUID / payment API key not set (Admin → Providers or .env).');
        }

        $start = microtime(true);
        try {
            $this->client->post('v1/payment/services', []);

            return new HealthCheckResult(HealthCheckResult::HEALTHY, 'Payment API reachable and credentials accepted.', (int) ((microtime(true) - $start) * 1000));
        } catch (ProviderException $e) {
            return new HealthCheckResult($e->retryable ? HealthCheckResult::DOWN : HealthCheckResult::DEGRADED, $e->getMessage(), (int) ((microtime(true) - $start) * 1000));
        }
    }

    /**
     * @return list<array{currency: string, network: string, is_available: bool, min: ?string, max: ?string, fee_percent: ?string, fee_fixed: ?string}>
     */
    public function listServices(string $direction): array
    {
        $result = $direction === 'payout'
            ? $this->client->post('v1/payout/services', [], CryptomusClient::KEY_PAYOUT)
            : $this->client->post('v1/payment/services', []);

        $services = [];
        foreach ($result as $row) {
            if (! is_array($row) || empty($row['currency'])) {
                continue;
            }
            $services[] = [
                'currency' => strtoupper((string) $row['currency']),
                'network' => (string) ($row['network'] ?? ''),
                'is_available' => (bool) ($row['is_available'] ?? false),
                'min' => Decimal::ofOrNull($row['limit']['min_amount'] ?? null),
                'max' => Decimal::ofOrNull($row['limit']['max_amount'] ?? null),
                'fee_percent' => isset($row['commission']['percent']) ? (string) $row['commission']['percent'] : null,
                'fee_fixed' => Decimal::ofOrNull($row['commission']['fee_amount'] ?? null),
            ];
        }

        return $services;
    }

    /** @param  array<string, mixed>  $r */
    private function toPaymentData(array $r): ProviderPaymentData
    {
        $status = (string) ($r['payment_status'] ?? $r['status'] ?? '');
        $expiredAt = isset($r['expired_at']) && is_numeric($r['expired_at'])
            ? CarbonImmutable::createFromTimestampUTC((int) $r['expired_at'])
            : null;
        $mapped = CryptomusStatusMap::payment($status, $expiredAt !== null && $expiredAt->isPast());

        $received = null;
        if ($mapped !== null && $mapped->isSettled()) {
            $received = $this->decimalOrNull($r['payment_amount'] ?? null);
        }

        return new ProviderPaymentData(
            providerStatus: $status,
            status: $mapped,
            providerPaymentId: isset($r['uuid']) ? (string) $r['uuid'] : null,
            checkoutUrl: $r['url'] ?? null,
            payAddress: $r['address'] ?? null,
            network: $r['network'] ?? null,
            receivedAmount: $received,
            receivedCurrency: isset($r['payer_currency']) ? strtoupper((string) $r['payer_currency']) : (isset($r['currency']) ? strtoupper((string) $r['currency']) : null),
            providerFee: $this->decimalOrNull($r['commission'] ?? null),
            txHash: $r['txid'] ?? null,
            payerAddress: $r['from'] ?? null,
            expiresAt: $expiredAt,
            isFinal: (bool) ($r['is_final'] ?? false),
            raw: $r,
        );
    }

    /** @param  array<string, mixed>  $r */
    private function toPayoutData(array $r): ProviderPayoutData
    {
        $status = (string) ($r['status'] ?? '');
        $mapped = CryptomusStatusMap::payout($status);

        return new ProviderPayoutData(
            providerStatus: $status,
            status: $mapped,
            providerPayoutId: isset($r['uuid']) ? (string) $r['uuid'] : null,
            txHash: $r['txid'] ?? null,
            providerFee: $this->decimalOrNull($r['commission'] ?? null),
            failureReason: $mapped === PayoutStatus::Failed ? ($r['fail_reason'] ?? 'Provider reported '.$status) : null,
            isFinal: (bool) ($r['is_final'] ?? false),
            raw: $r,
        );
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_int($value) || is_float($value) || is_string($value) ? Decimal::of($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

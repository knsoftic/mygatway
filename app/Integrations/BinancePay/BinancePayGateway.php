<?php

namespace App\Integrations\BinancePay;

use App\Enums\PaymentStatus;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
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
use App\Integrations\Support\RawDecimal;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Binance Pay Merchant API adapter.
 *
 * Endpoints used (developers.binance.com/en/docs/products/binance-pay-merchant):
 *  POST /binancepay/openapi/v3/order            create order (C2B acquiring)
 *  POST /binancepay/openapi/v2/order/query      order status
 *  POST /binancepay/openapi/order/refund        refund (partial allowed)
 *  POST /binancepay/openapi/payout/transfer     batch payout to Binance users
 *  POST /binancepay/openapi/payout/query        payout status
 *  POST /binancepay/openapi/certificates        webhook verification public key
 *  POST /binancepay/openapi/v2/balance          wallet balance (health check)
 *
 * Binance Pay payouts go to Binance accounts (Binance ID, or email for whitelisted
 * merchants) - NOT to external blockchain addresses.
 */
class BinancePayGateway implements PaymentGateway
{
    public function __construct(private readonly BinancePayClient $client, private readonly array $config) {}

    public function code(): string
    {
        return 'binance_pay';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            payIn: true,
            payOut: true,
            refund: true,
            partialRefund: true,
            webhooks: true,
            statusQuery: true,
            providerSandbox: false,
            payoutDestinationTypes: [PayoutDestinationType::BinanceId, PayoutDestinationType::Email],
            payoutRequiresNetwork: false,
            notes: [
                'Customers pay from their Binance account via hosted checkout, QR code or deeplink.',
                'Payouts are internal Binance transfers to a Binance ID; email payouts need Binance whitelisting.',
                'Payouts to blockchain wallet addresses are not offered by the Binance Pay Merchant API.',
                'Binance Pay has no public sandbox environment.',
            ],
        );
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function createPayment(Payment $payment, array $options): ProviderPaymentData
    {
        $label = $this->sanitizeName($payment->description ?: 'Order '.$payment->merchant_reference);

        $payload = array_filter([
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => $payment->provider_order_id,
            'orderAmount' => new RawDecimal($payment->amount),
            'currency' => $payment->currency,
            'description' => mb_substr($label, 0, 256),
            'goodsDetails' => [[
                'goodsType' => '02',
                'goodsCategory' => 'Z000',
                'referenceGoodsId' => substr(preg_replace('/[^A-Za-z0-9]/', '', $payment->merchant_reference) ?: $payment->provider_order_id, 0, 32),
                'goodsName' => mb_substr($label, 0, 256),
            ]],
            'returnUrl' => $payment->success_url,
            'cancelUrl' => $payment->cancel_url,
            // v3: how long the order is valid, in milliseconds (default 1h, max 15 days).
            'orderExpireTime' => $options['lifetime'] * 1000,
            'passThroughInfo' => $payment->public_id,
            'webhookUrl' => $options['callback_url'],
        ], fn ($v) => $v !== null);

        $data = (array) $this->client->post('binancepay/openapi/v3/order', $payload);

        return new ProviderPaymentData(
            providerStatus: 'INITIAL',
            status: PaymentStatus::Pending,
            providerPaymentId: isset($data['prepayId']) ? (string) $data['prepayId'] : null,
            checkoutUrl: $data['checkoutUrl'] ?? ($data['universalUrl'] ?? null),
            expiresAt: isset($data['expireTime']) ? CarbonImmutable::createFromTimestampMsUTC((int) $data['expireTime']) : null,
            raw: $data,
        );
    }

    public function fetchPayment(Payment $payment): ProviderPaymentData
    {
        $payload = $payment->provider_payment_id
            ? ['prepayId' => $payment->provider_payment_id]
            : ['merchantTradeNo' => $payment->provider_order_id];

        $d = (array) $this->client->post('binancepay/openapi/v2/order/query', $payload);
        $status = (string) ($d['status'] ?? '');
        $mapped = BinancePayStatusMap::order($status);

        return new ProviderPaymentData(
            providerStatus: $status,
            status: $mapped,
            providerPaymentId: isset($d['prepayId']) ? (string) $d['prepayId'] : null,
            receivedAmount: $mapped?->isSettled() ? $this->decimal($d['orderAmount'] ?? null) : null,
            receivedCurrency: isset($d['currency']) ? strtoupper((string) $d['currency']) : null,
            providerFee: $this->decimal($d['commission'] ?? null),
            txHash: isset($d['transactionId']) ? (string) $d['transactionId'] : null,
            isFinal: $mapped !== null && $mapped->isFinal(),
            raw: $d,
        );
    }

    public function createPayout(Payout $payout, string $callbackUrl): ProviderPayoutData
    {
        $receiveType = match ($payout->destination_type) {
            PayoutDestinationType::BinanceId => 'BINANCE_ID',
            PayoutDestinationType::Email => 'EMAIL',
            default => throw new UnsupportedOperationException('binance_pay', 'payouts to '.$payout->destination_type->value,
                'Binance Pay pays out to Binance accounts only.'),
        };

        $payload = [
            'requestId' => $payout->provider_order_id,
            'batchName' => substr('Payout '.$payout->provider_order_id, 0, 32),
            'currency' => $payout->currency,
            'totalAmount' => new RawDecimal($payout->amount),
            'totalNumber' => 1,
            'webhookUrl' => $callbackUrl,
            'transferDetailList' => [array_filter([
                'merchantSendId' => $payout->provider_order_id,
                'transferAmount' => new RawDecimal($payout->amount),
                'receiveType' => $receiveType,
                'receiver' => $payout->destination,
                'transferMethod' => $this->config['payout_wallet'] ?? 'FUNDING_WALLET',
                'remark' => $payout->description ? mb_substr($payout->description, 0, 128) : null,
            ], fn ($v) => $v !== null)],
        ];

        $data = (array) $this->client->post('binancepay/openapi/payout/transfer', $payload);

        return new ProviderPayoutData(
            providerStatus: (string) ($data['status'] ?? 'ACCEPTED'),
            status: PayoutStatus::Processing,
            providerPayoutId: (string) ($data['requestId'] ?? $payout->provider_order_id),
            raw: $data,
        );
    }

    public function fetchPayout(Payout $payout): ProviderPayoutData
    {
        $d = (array) $this->client->post('binancepay/openapi/payout/query', ['requestId' => $payout->provider_order_id]);
        $batchStatus = (string) ($d['batchStatus'] ?? '');
        $detail = collect($d['transferDetailList'] ?? [])->first(fn ($row) => ($row['merchantSendId'] ?? null) === $payout->provider_order_id)
            ?? ($d['transferDetailList'][0] ?? null);
        $detailStatus = is_array($detail) ? ($detail['status'] ?? null) : null;
        $mapped = BinancePayStatusMap::payout($batchStatus, $detailStatus);

        return new ProviderPayoutData(
            providerStatus: $detailStatus ? "{$batchStatus}/{$detailStatus}" : $batchStatus,
            status: $mapped,
            providerPayoutId: $payout->provider_order_id,
            txHash: is_array($detail) && isset($detail['orderId']) ? (string) $detail['orderId'] : null,
            providerFee: $this->decimal(is_array($detail) ? ($detail['payoutFee'] ?? null) : ($d['totalPayoutFee'] ?? null)),
            failureReason: $mapped === PayoutStatus::Failed ? 'Binance Pay reported '.($detailStatus ?? $batchStatus) : null,
            isFinal: $mapped !== null && $mapped !== PayoutStatus::Processing,
            raw: $d,
        );
    }

    public function refundPayment(Refund $refund, Payment $payment): ProviderRefundData
    {
        if (! $payment->provider_payment_id) {
            throw new ProviderException('Missing prepayId for Binance refund', 'binance_pay', 'provider_error',
                'This payment cannot be refunded automatically.');
        }

        $d = (array) $this->client->post('binancepay/openapi/order/refund', array_filter([
            'refundRequestId' => $refund->provider_request_id,
            'prepayId' => $payment->provider_payment_id,
            'refundAmount' => new RawDecimal($refund->amount),
            'refundReason' => $refund->reason ? mb_substr($refund->reason, 0, 256) : null,
        ], fn ($v) => $v !== null));

        $status = (string) ($d['refundStatus'] ?? 'INITIAL');

        return new ProviderRefundData($status, BinancePayStatusMap::refund($status), isset($d['refundId']) ? (string) $d['refundId'] : null, $d);
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        $timestamp = (string) $request->header('BinancePay-Timestamp', '');
        $nonce = (string) $request->header('BinancePay-Nonce', '');
        $signature = (string) $request->header('BinancePay-Signature', '');
        $serial = (string) $request->header('BinancePay-Certificate-SN', '');
        $body = $request->getContent();

        if ($timestamp === '' || $nonce === '' || $signature === '' || $body === '') {
            throw new InvalidWebhookException('Binance Pay webhook is missing signature headers', 400);
        }

        try {
            $certs = $this->client->certificates();
            if ($serial !== '' && ! isset($certs[$serial])) {
                $certs = $this->client->certificates(refresh: true);
            }
        } catch (ProviderException $e) {
            // Cannot verify right now; a 5xx makes Binance retry (up to 6 times).
            throw new InvalidWebhookException('Unable to load Binance Pay certificates: '.$e->getMessage(), 503);
        }

        $candidates = $serial !== '' && isset($certs[$serial]) ? [$certs[$serial]] : array_values($certs);
        $verified = false;
        foreach ($candidates as $publicKey) {
            if (BinancePaySignature::verifyWebhook($timestamp, $nonce, $body, $signature, $publicKey)) {
                $verified = true;
                break;
            }
        }

        if (! $verified) {
            throw new InvalidWebhookException('Binance Pay webhook signature mismatch');
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            throw new InvalidWebhookException('Binance Pay webhook body is not valid JSON', 400);
        }

        $bizType = (string) ($payload['bizType'] ?? '');
        $bizStatus = (string) ($payload['bizStatus'] ?? '');
        $bizId = (string) ($payload['bizIdStr'] ?? ($payload['bizId'] ?? ''));
        $data = is_string($payload['data'] ?? null) ? json_decode($payload['data'], true) : ($payload['data'] ?? []);
        $data = is_array($data) ? $data : [];
        $ack = ['returnCode' => 'SUCCESS', 'returnMessage' => null];

        if ($bizType === 'PAY') {
            $mapped = BinancePayStatusMap::orderWebhook($bizStatus);

            return new ProviderWebhookData(
                kind: ProviderWebhookData::KIND_PAYMENT,
                eventType: 'PAY.'.$bizStatus,
                reference: isset($data['merchantTradeNo']) ? (string) $data['merchantTradeNo'] : null,
                externalId: $bizId,
                payment: new ProviderPaymentData(
                    providerStatus: $bizStatus,
                    status: $mapped,
                    providerPaymentId: $bizId !== '' ? $bizId : null,
                    receivedAmount: $mapped?->isSettled() ? $this->decimal($data['totalFee'] ?? null) : null,
                    receivedCurrency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
                    providerFee: $this->decimal($data['commission'] ?? null),
                    txHash: isset($data['transactionId']) ? (string) $data['transactionId'] : null,
                    isFinal: $mapped !== null,
                    raw: $payload,
                ),
                acknowledgement: $ack,
                // PAY_CLOSED does not tell us whether the order was cancelled or expired.
                requiresStatusQuery: $mapped === null,
            );
        }

        if ($bizType === 'PAYOUT') {
            return new ProviderWebhookData(
                kind: ProviderWebhookData::KIND_PAYOUT,
                eventType: 'PAYOUT.'.$bizStatus,
                reference: isset($data['requestId']) ? (string) $data['requestId'] : null,
                externalId: $bizId,
                payout: new ProviderPayoutData(
                    providerStatus: $bizStatus,
                    status: BinancePayStatusMap::payout($bizStatus, null),
                    providerPayoutId: isset($data['requestId']) ? (string) $data['requestId'] : null,
                    raw: $payload,
                ),
                acknowledgement: $ack,
                // The batch notification has no per-transfer fee/status; always confirm via payout query.
                requiresStatusQuery: true,
            );
        }

        return new ProviderWebhookData(ProviderWebhookData::KIND_UNKNOWN, $bizType.'.'.$bizStatus, null, $bizId, acknowledgement: $ack);
    }

    public function healthCheck(): HealthCheckResult
    {
        if (! $this->isConfigured()) {
            return new HealthCheckResult(HealthCheckResult::UNCONFIGURED, 'API key / secret key not set (Admin → Providers or .env).');
        }

        $start = microtime(true);
        try {
            $this->client->post('binancepay/openapi/v2/balance', ['wallet' => $this->config['payout_wallet'] ?? 'FUNDING_WALLET']);

            return new HealthCheckResult(HealthCheckResult::HEALTHY, 'API reachable and credentials accepted.', (int) ((microtime(true) - $start) * 1000));
        } catch (ProviderException $e) {
            return new HealthCheckResult($e->retryable ? HealthCheckResult::DOWN : HealthCheckResult::DEGRADED, $e->getMessage(), (int) ((microtime(true) - $start) * 1000));
        }
    }

    /** Binance rejects special characters and emoji in goods names. */
    private function sanitizeName(string $value): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 \-]/', ' ', $value)));

        return $clean !== '' ? $clean : 'Payment';
    }

    private function decimal(mixed $value): ?string
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

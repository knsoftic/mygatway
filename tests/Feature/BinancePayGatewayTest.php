<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Integrations\BinancePay\BinancePayClient;
use App\Integrations\BinancePay\BinancePayGateway;
use App\Integrations\BinancePay\BinancePayStatusMap;
use App\Integrations\Data\ProviderWebhookData;
use App\Integrations\Exceptions\InvalidWebhookException;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Binance Pay adapter against the documented Merchant API contract.
 * All HTTP is faked; webhook signatures use a locally generated RSA key pair.
 */
class BinancePayGatewayTest extends TestCase
{
    private const API_KEY = 'test-api-key';

    private const SECRET = 'test-secret-key';

    private const CERT_SERIAL = 'cert-serial-1';

    private function gateway(): BinancePayGateway
    {
        $config = [
            'base_url' => 'https://bpay.binanceapi.com',
            'api_key' => self::API_KEY,
            'secret_key' => self::SECRET,
            'payout_wallet' => 'FUNDING_WALLET',
            'timeout' => 5,
        ];

        return new BinancePayGateway(new BinancePayClient(Http::getFacadeRoot(), Cache::store('array'), $config), $config);
    }

    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} private key and PEM public key */
    private function keyPair(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows PHP builds need an explicit openssl.cnf; it ships in extras/ssl.
        $cnf = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $options['config'] = $cnf;
        }
        $key = openssl_pkey_new($options);
        if ($key === false) {
            $this->markTestSkipped('OpenSSL key generation is not available: '.openssl_error_string());
        }

        return [$key, openssl_pkey_get_details($key)['key']];
    }

    private function signedWebhook(array $payload, \OpenSSLAsymmetricKey $privateKey): Request
    {
        $body = json_encode($payload);
        $timestamp = (string) (time() * 1000);
        $nonce = str_repeat('a', 32);
        openssl_sign($timestamp."\n".$nonce."\n".$body."\n", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return Request::create('/api/webhooks/binance_pay', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BINANCEPAY_TIMESTAMP' => $timestamp,
            'HTTP_BINANCEPAY_NONCE' => $nonce,
            'HTTP_BINANCEPAY_SIGNATURE' => base64_encode($signature),
            'HTTP_BINANCEPAY_CERTIFICATE_SN' => self::CERT_SERIAL,
        ], content: $body);
    }

    private function fakeCertificates(string $publicPem): void
    {
        Http::fake(['bpay.binanceapi.com/binancepay/openapi/certificates' => Http::response([
            'status' => 'SUCCESS', 'code' => '000000', 'data' => [['certSerial' => self::CERT_SERIAL, 'certPublic' => $publicPem]],
        ])]);
    }

    public function test_create_order_sends_signed_v3_request(): void
    {
        Http::fake(['bpay.binanceapi.com/binancepay/openapi/v3/order' => Http::response(['status' => 'SUCCESS', 'code' => '000000', 'data' => [
            'prepayId' => '29383937493038367292', 'checkoutUrl' => 'https://pay.binance.com/checkout/abc', 'expireTime' => now()->addHour()->getTimestampMs(),
        ]])]);

        $payment = (new Payment)->forceFill(['public_id' => 'pay_t', 'provider_order_id' => 'pay01binanceorder', 'amount' => '25.5', 'currency' => 'USDT',
            'merchant_reference' => 'order-1001', 'description' => 'Order #1001 🚀']);
        $data = $this->gateway()->createPayment($payment, ['callback_url' => 'https://gw.example/api/webhooks/binance_pay', 'lifetime' => 1800]);

        Http::assertSent(function (ClientRequest $r) {
            $ts = $r->header('BinancePay-Timestamp')[0];
            $nonce = $r->header('BinancePay-Nonce')[0];
            $expected = strtoupper(hash_hmac('sha512', $ts."\n".$nonce."\n".$r->body()."\n", self::SECRET));
            $json = json_decode($r->body(), true);

            return $r->header('BinancePay-Certificate-SN')[0] === self::API_KEY
                && strlen($nonce) === 32
                && $r->header('BinancePay-Signature')[0] === $expected
                && $json['merchantTradeNo'] === 'pay01binanceorder'
                && $json['orderExpireTime'] === 1800000
                && $json['env'] === ['terminalType' => 'WEB']
                && ! preg_match('/[^A-Za-z0-9 \-]/', $json['goodsDetails'][0]['goodsName'])
                && str_contains($r->body(), '"orderAmount":25.5');
        });
        $this->assertSame(PaymentStatus::Pending, $data->status);
        $this->assertSame('29383937493038367292', $data->providerPaymentId);
        $this->assertSame('https://pay.binance.com/checkout/abc', $data->checkoutUrl);
    }

    public function test_valid_pay_success_webhook_is_parsed_and_acknowledged(): void
    {
        [$private, $public] = $this->keyPair();
        $this->fakeCertificates($public);

        $hook = $this->gateway()->parseWebhook($this->signedWebhook([
            'bizType' => 'PAY', 'bizId' => 29383937493038367292, 'bizIdStr' => '29383937493038367292', 'bizStatus' => 'PAY_SUCCESS',
            'data' => json_encode(['merchantTradeNo' => 'pay01binanceorder', 'totalFee' => 25.5, 'currency' => 'USDT', 'commission' => 0.1, 'transactionId' => 'M_P_1']),
        ], $private));

        $this->assertSame(ProviderWebhookData::KIND_PAYMENT, $hook->kind);
        $this->assertSame('pay01binanceorder', $hook->reference);
        $this->assertSame(PaymentStatus::Completed, $hook->payment->status);
        $this->assertSame('25.50000000', $hook->payment->receivedAmount);
        $this->assertSame(['returnCode' => 'SUCCESS', 'returnMessage' => null], $hook->acknowledgement);
    }

    public function test_pay_closed_requires_a_status_query(): void
    {
        [$private, $public] = $this->keyPair();
        $this->fakeCertificates($public);

        $hook = $this->gateway()->parseWebhook($this->signedWebhook([
            'bizType' => 'PAY', 'bizIdStr' => '1', 'bizStatus' => 'PAY_CLOSED', 'data' => json_encode(['merchantTradeNo' => 'x']),
        ], $private));

        $this->assertNull($hook->payment->status);
        $this->assertTrue($hook->requiresStatusQuery);
    }

    public function test_webhook_signed_by_another_key_is_rejected(): void
    {
        [, $public] = $this->keyPair();
        [$attacker] = $this->keyPair();
        $this->fakeCertificates($public);

        $this->expectException(InvalidWebhookException::class);
        $this->gateway()->parseWebhook($this->signedWebhook(['bizType' => 'PAY', 'bizStatus' => 'PAY_SUCCESS', 'data' => '{}'], $attacker));
    }

    public function test_webhook_without_signature_headers_is_rejected(): void
    {
        Http::fake();
        $this->expectException(InvalidWebhookException::class);
        $this->gateway()->parseWebhook(Request::create('/', 'POST', content: '{"bizType":"PAY"}'));
    }

    public function test_payouts_go_to_binance_accounts_only(): void
    {
        Http::fake(['bpay.binanceapi.com/binancepay/openapi/payout/transfer' => Http::response(['status' => 'SUCCESS', 'code' => '000000',
            'data' => ['requestId' => 'po01binance', 'status' => 'ACCEPTED']])]);

        $payout = (new Payout)->forceFill(['provider_order_id' => 'po01binance', 'amount' => '10', 'currency' => 'USDT',
            'destination_type' => PayoutDestinationType::BinanceId, 'destination' => '123456789']);
        $data = $this->gateway()->createPayout($payout, 'https://gw.example/api/webhooks/binance_pay');

        Http::assertSent(function (ClientRequest $r) {
            $json = json_decode($r->body(), true);
            $detail = $json['transferDetailList'][0];

            return $json['requestId'] === 'po01binance' && $json['totalNumber'] === 1 && strlen($json['batchName']) <= 32
                && $detail['receiveType'] === 'BINANCE_ID' && $detail['receiver'] === '123456789' && $detail['transferMethod'] === 'FUNDING_WALLET';
        });
        $this->assertSame(PayoutStatus::Processing, $data->status);

        $this->expectException(UnsupportedOperationException::class);
        $this->gateway()->createPayout((new Payout)->forceFill(['destination_type' => PayoutDestinationType::CryptoAddress, 'destination' => 'TQ5n']), 'https://x.example');
    }

    public function test_documented_statuses_are_mapped(): void
    {
        $this->assertSame(PaymentStatus::Pending, BinancePayStatusMap::order('INITIAL'));
        $this->assertSame(PaymentStatus::Completed, BinancePayStatusMap::order('PAID'));
        $this->assertSame(PaymentStatus::Expired, BinancePayStatusMap::order('EXPIRED'));
        $this->assertSame(PaymentStatus::Refunded, BinancePayStatusMap::order('FULL_REFUNDED'));
        $this->assertSame(PayoutStatus::Completed, BinancePayStatusMap::payout('SUCCESS', null));
        $this->assertSame(PayoutStatus::Failed, BinancePayStatusMap::payout('PART_SUCCESS', 'FAIL'));
        $this->assertSame(PayoutStatus::Processing, BinancePayStatusMap::payout('ACCEPTED', null));
    }

    public function test_provider_error_codes_are_mapped(): void
    {
        Http::fake(['bpay.binanceapi.com/*' => Http::sequence()
            ->push(['status' => 'FAIL', 'code' => '400611', 'errorMessage' => 'insufficient balance'], 400)
            ->push(['status' => 'FAIL', 'code' => '400000', 'errorMessage' => 'unknown error'], 400)]);

        $payment = (new Payment)->forceFill(['provider_order_id' => 'x', 'provider_payment_id' => '1']);
        foreach (['provider_insufficient_funds' => false, 'provider_unavailable' => true] as $code => $retryable) {
            try {
                $this->gateway()->fetchPayment($payment);
                $this->fail('Expected ProviderException');
            } catch (ProviderException $e) {
                $this->assertSame($code, $e->errorCode);
                $this->assertSame($retryable, $e->retryable);
            }
        }
    }
}

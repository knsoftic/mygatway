<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PayoutDestinationType;
use App\Enums\PayoutStatus;
use App\Integrations\Cryptomus\CryptomusClient;
use App\Integrations\Cryptomus\CryptomusGateway;
use App\Integrations\Cryptomus\CryptomusSignature;
use App\Integrations\Cryptomus\CryptomusStatusMap;
use App\Integrations\Data\ProviderWebhookData;
use App\Integrations\Exceptions\InvalidWebhookException;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cryptomus adapter against the documented API contract (doc.cryptomus.com).
 * All HTTP is faked; no real provider calls are made.
 */
class CryptomusGatewayTest extends TestCase
{
    private const PAYMENT_KEY = 'test-payment-key';

    private const PAYOUT_KEY = 'test-payout-key';

    private const MERCHANT = '8b03432e-385b-4670-8d06-064591096795';

    private function gateway(array $overrides = []): CryptomusGateway
    {
        $config = array_merge([
            'base_url' => 'https://api.cryptomus.com',
            'merchant_id' => self::MERCHANT,
            'payment_key' => self::PAYMENT_KEY,
            'payout_key' => self::PAYOUT_KEY,
            'webhook_ips' => ['91.227.144.54'],
            'enforce_webhook_ip' => true,
            'timeout' => 5,
        ], $overrides);

        return new CryptomusGateway(new CryptomusClient(Http::getFacadeRoot(), $config), $config);
    }

    private function payment(): Payment
    {
        return (new Payment)->forceFill([
            'public_id' => 'pay_test1',
            'provider_order_id' => 'pay01testorder',
            'amount' => '49.90',
            'currency' => 'USDT',
            'network' => 'tron',
            'success_url' => 'https://shop.example/thanks',
            'cancel_url' => 'https://shop.example/cart',
        ]);
    }

    /** Builds a webhook exactly as Cryptomus does: PHP json_encode with escaped slashes. */
    private function webhookRequest(array $payload, string $key, string $ip = '91.227.144.54'): Request
    {
        $payload['sign'] = CryptomusSignature::sign(json_encode($payload, JSON_UNESCAPED_UNICODE), $key);

        return Request::create('/api/webhooks/cryptomus', 'POST', server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function test_create_payment_sends_signed_documented_request(): void
    {
        Http::fake(['api.cryptomus.com/v1/payment' => Http::response(['state' => 0, 'result' => [
            'uuid' => '26109ba0-b05b-4ee0-93d1-fd62c822ce95', 'order_id' => 'pay01testorder', 'url' => 'https://pay.cryptomus.com/pay/26109ba0',
            'payment_status' => 'check', 'address' => 'TXYZ', 'network' => 'tron', 'expired_at' => now()->addHour()->timestamp, 'is_final' => false,
        ]])]);

        $data = $this->gateway()->createPayment($this->payment(), ['callback_url' => 'https://gw.example/api/webhooks/cryptomus', 'lifetime' => 3600]);

        Http::assertSent(function (ClientRequest $request) {
            $body = $request->body();
            $json = json_decode($body, true);

            return $request->url() === 'https://api.cryptomus.com/v1/payment'
                && $request->header('merchant')[0] === self::MERCHANT
                && $request->header('sign')[0] === md5(base64_encode($body).self::PAYMENT_KEY)
                && $json['amount'] === '49.9'
                && $json['currency'] === 'USDT'
                && $json['order_id'] === 'pay01testorder'
                && $json['url_callback'] === 'https://gw.example/api/webhooks/cryptomus'
                && $json['lifetime'] === 3600
                && $json['currencies'] === [['currency' => 'USDT', 'network' => 'tron']];
        });
        $this->assertSame(PaymentStatus::Pending, $data->status);
        $this->assertSame('26109ba0-b05b-4ee0-93d1-fd62c822ce95', $data->providerPaymentId);
        $this->assertSame('https://pay.cryptomus.com/pay/26109ba0', $data->checkoutUrl);
    }

    public function test_requests_without_parameters_sign_an_empty_body(): void
    {
        Http::fake(['api.cryptomus.com/v1/payment/services' => Http::response(['state' => 0, 'result' => []])]);

        $this->gateway()->healthCheck();

        Http::assertSent(fn (ClientRequest $r) => $r->body() === '' && $r->header('sign')[0] === md5(base64_encode('').self::PAYMENT_KEY));
    }

    public function test_payouts_are_signed_with_the_payout_key(): void
    {
        Http::fake(['api.cryptomus.com/v1/payout' => Http::response(['state' => 0, 'result' => ['uuid' => 'po-uuid', 'status' => 'process', 'is_final' => false]])]);

        $payout = (new Payout)->forceFill([
            'provider_order_id' => 'po01test', 'amount' => '10', 'currency' => 'USDT', 'network' => 'tron',
            'destination_type' => PayoutDestinationType::CryptoAddress, 'destination' => 'TQ5nAddressxxxxxxxxxxxxxxxx',
        ]);
        $data = $this->gateway()->createPayout($payout, 'https://gw.example/api/webhooks/cryptomus');

        Http::assertSent(function (ClientRequest $r) {
            $json = json_decode($r->body(), true);

            return $r->header('sign')[0] === md5(base64_encode($r->body()).self::PAYOUT_KEY)
                && $json['address'] === 'TQ5nAddressxxxxxxxxxxxxxxxx' && $json['is_subtract'] === true && $json['network'] === 'tron';
        });
        $this->assertSame(PayoutStatus::Processing, $data->status);
    }

    public function test_payouts_to_binance_ids_are_refused_without_calling_the_api(): void
    {
        Http::fake();
        $this->expectException(UnsupportedOperationException::class);

        try {
            $this->gateway()->createPayout((new Payout)->forceFill(['destination_type' => PayoutDestinationType::BinanceId, 'destination' => '123456']), 'https://x.example');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_valid_paid_webhook_is_parsed(): void
    {
        $request = $this->webhookRequest([
            'type' => 'payment', 'uuid' => 'inv-1', 'order_id' => 'pay01testorder', 'amount' => '49.90', 'payment_amount' => '49.90',
            'payer_currency' => 'USDT', 'currency' => 'USDT', 'network' => 'tron', 'status' => 'paid', 'is_final' => true,
            'txid' => '0xabc', 'from' => 'TPayer', 'url' => 'https://pay.cryptomus.com/pay/inv-1',
        ], self::PAYMENT_KEY);

        $hook = $this->gateway()->parseWebhook($request);

        $this->assertSame(ProviderWebhookData::KIND_PAYMENT, $hook->kind);
        $this->assertSame('pay01testorder', $hook->reference);
        $this->assertSame(PaymentStatus::Completed, $hook->payment->status);
        $this->assertSame('49.90000000', $hook->payment->receivedAmount);
        $this->assertSame('0xabc', $hook->payment->txHash);
    }

    public function test_tampered_webhook_is_rejected(): void
    {
        $request = $this->webhookRequest(['type' => 'payment', 'uuid' => 'inv-1', 'order_id' => 'o1', 'status' => 'check'], self::PAYMENT_KEY);
        $tampered = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '91.227.144.54'],
            content: str_replace('"check"', '"paid"', $request->getContent()));

        $this->expectException(InvalidWebhookException::class);
        $this->gateway()->parseWebhook($tampered);
    }

    public function test_webhook_signed_with_wrong_key_is_rejected(): void
    {
        $this->expectException(InvalidWebhookException::class);
        $this->gateway()->parseWebhook($this->webhookRequest(['type' => 'payment', 'uuid' => 'x', 'status' => 'paid'], 'attacker-key'));
    }

    public function test_webhook_from_unexpected_ip_is_rejected(): void
    {
        try {
            $this->gateway()->parseWebhook($this->webhookRequest(['type' => 'payment', 'uuid' => 'x', 'status' => 'paid'], self::PAYMENT_KEY, '203.0.113.9'));
            $this->fail('Expected an exception');
        } catch (InvalidWebhookException $e) {
            $this->assertSame(403, $e->httpStatus);
        }
    }

    public function test_payout_webhook_accepts_either_key(): void
    {
        $hook = $this->gateway()->parseWebhook($this->webhookRequest(
            ['type' => 'payout', 'uuid' => 'po-uuid', 'order_id' => 'po01test', 'status' => 'paid', 'is_final' => true, 'txid' => '0xdef'],
            self::PAYOUT_KEY,
        ));

        $this->assertSame(ProviderWebhookData::KIND_PAYOUT, $hook->kind);
        $this->assertSame(PayoutStatus::Completed, $hook->payout->status);
    }

    public function test_documented_payment_statuses_are_mapped(): void
    {
        $this->assertSame(PaymentStatus::Pending, CryptomusStatusMap::payment('check'));
        $this->assertSame(PaymentStatus::Confirming, CryptomusStatusMap::payment('confirm_check'));
        $this->assertSame(PaymentStatus::Completed, CryptomusStatusMap::payment('paid_over'));
        $this->assertSame(PaymentStatus::Underpaid, CryptomusStatusMap::payment('wrong_amount'));
        $this->assertSame(PaymentStatus::Processing, CryptomusStatusMap::payment('wrong_amount_waiting'));
        $this->assertSame(PaymentStatus::Expired, CryptomusStatusMap::payment('cancel', expired: true));
        $this->assertSame(PaymentStatus::OnHold, CryptomusStatusMap::payment('locked'));
        $this->assertSame(PaymentStatus::Refunded, CryptomusStatusMap::payment('refund_paid'));
        $this->assertNull(CryptomusStatusMap::payment('something_new'));
        $this->assertSame(PayoutStatus::Failed, CryptomusStatusMap::payout('system_fail'));
    }

    public function test_provider_errors_are_mapped_to_safe_codes(): void
    {
        Http::fake(['api.cryptomus.com/*' => Http::sequence()
            ->push(['state' => 1, 'message' => 'Not enough funds'], 422)
            ->push(['state' => 1, 'message' => 'Server error'], 500)
            ->push(['state' => 1, 'message' => 'Invalid Merchant Uuid'], 401)]);

        foreach (['provider_insufficient_funds' => false, 'provider_unavailable' => true, 'provider_auth_failed' => false] as $code => $retryable) {
            try {
                $this->gateway()->fetchPayment($this->payment());
                $this->fail('Expected ProviderException');
            } catch (ProviderException $e) {
                $this->assertSame($code, $e->errorCode);
                $this->assertSame($retryable, $e->retryable);
            }
        }
    }
}

<?php

namespace App\Integrations\Contracts;

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
use Illuminate\Http\Request;

/**
 * Common interface every provider adapter implements. Each adapter talks only
 * to the endpoints documented by its provider; operations a provider does not
 * support throw UnsupportedOperationException instead of being emulated.
 */
interface PaymentGateway
{
    public function code(): string;

    public function capabilities(): ProviderCapabilities;

    /** True when the credentials this adapter needs are present in the environment. */
    public function isConfigured(): bool;

    /**
     * @param  array{lifetime: int, callback_url: string}  $options
     *
     * @throws ProviderException
     */
    public function createPayment(Payment $payment, array $options): ProviderPaymentData;

    /** Authenticated server-to-server status query. @throws ProviderException */
    public function fetchPayment(Payment $payment): ProviderPaymentData;

    /** @throws ProviderException|UnsupportedOperationException */
    public function createPayout(Payout $payout, string $callbackUrl): ProviderPayoutData;

    /** @throws ProviderException|UnsupportedOperationException */
    public function fetchPayout(Payout $payout): ProviderPayoutData;

    /** @throws ProviderException|UnsupportedOperationException */
    public function refundPayment(Refund $refund, Payment $payment): ProviderRefundData;

    /**
     * Verify authenticity (signature / source) and normalise an inbound callback.
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(Request $request): ProviderWebhookData;

    public function healthCheck(): HealthCheckResult;
}

<?php

namespace App\Integrations\Sandbox;

use App\Enums\PaymentStatus;
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
use App\Integrations\Exceptions\UnsupportedOperationException;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * TEST MODE ONLY. Neither Cryptomus nor Binance Pay offers a sandbox, so test-mode
 * API keys are served by this simulator. It never contacts a provider and never
 * moves real funds. It mirrors the selected provider's capabilities so merchants
 * hit the same validation rules they will see in live mode.
 */
class SandboxGateway implements PaymentGateway
{
    public const OUTCOMES = ['paid', 'underpaid', 'failed', 'expired'];

    public function __construct(private readonly PaymentGateway $mirror) {}

    public function code(): string
    {
        return $this->mirror->code();
    }

    public function capabilities(): ProviderCapabilities
    {
        return $this->mirror->capabilities();
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createPayment(Payment $payment, array $options): ProviderPaymentData
    {
        $expiresAt = CarbonImmutable::now()->addSeconds($options['lifetime']);

        return new ProviderPaymentData(
            providerStatus: 'sandbox_pending',
            status: PaymentStatus::Pending,
            providerPaymentId: 'sbx_'.Str::lower(Str::random(24)),
            checkoutUrl: URL::temporarySignedRoute('sandbox.checkout', $expiresAt->addDay(), ['payment' => $payment->public_id]),
            payAddress: 'SANDBOX-'.strtoupper(Str::random(30)),
            network: $payment->network,
            expiresAt: $expiresAt,
        );
    }

    public function fetchPayment(Payment $payment): ProviderPaymentData
    {
        // The simulator's state is whatever the last simulated outcome recorded.
        return new ProviderPaymentData(
            providerStatus: $payment->provider_status ?? 'sandbox_pending',
            status: null,
            providerPaymentId: $payment->provider_payment_id,
        );
    }

    /** Produce the data a provider would report for the chosen outcome. */
    public function simulate(Payment $payment, string $outcome): ProviderPaymentData
    {
        $received = match ($outcome) {
            'paid' => $payment->amount,
            'underpaid' => Decimal::percentOf($payment->amount, '90'),
            default => null,
        };

        return new ProviderPaymentData(
            providerStatus: 'sandbox_'.$outcome,
            status: match ($outcome) {
                'paid' => PaymentStatus::Completed,
                'underpaid' => PaymentStatus::Underpaid,
                'failed' => PaymentStatus::Failed,
                'expired' => PaymentStatus::Expired,
            },
            providerPaymentId: $payment->provider_payment_id,
            receivedAmount: $received,
            receivedCurrency: $received !== null ? $payment->currency : null,
            providerFee: $received !== null ? Decimal::zero() : null,
            txHash: $received !== null ? 'sandbox_tx_'.Str::lower(Str::random(40)) : null,
            isFinal: true,
        );
    }

    public function createPayout(Payout $payout, string $callbackUrl): ProviderPayoutData
    {
        if (! $this->capabilities()->supportsDestination($payout->destination_type)) {
            throw new UnsupportedOperationException($this->code(), 'payouts to '.$payout->destination_type->value);
        }

        // Destinations containing "fail" simulate a provider-side failure.
        $fails = Str::contains(Str::lower($payout->destination), 'fail');

        return new ProviderPayoutData(
            providerStatus: $fails ? 'sandbox_failed' : 'sandbox_paid',
            status: $fails ? PayoutStatus::Failed : PayoutStatus::Completed,
            providerPayoutId: 'sbx_'.Str::lower(Str::random(24)),
            txHash: $fails ? null : 'sandbox_tx_'.Str::lower(Str::random(40)),
            providerFee: Decimal::zero(),
            failureReason: $fails ? 'Simulated provider failure (sandbox)' : null,
            isFinal: true,
        );
    }

    public function fetchPayout(Payout $payout): ProviderPayoutData
    {
        return new ProviderPayoutData($payout->provider_status ?? 'sandbox_pending', null, $payout->provider_payout_id);
    }

    public function refundPayment(Refund $refund, Payment $payment): ProviderRefundData
    {
        return new ProviderRefundData('sandbox_refunded', RefundStatus::Completed, 'sbx_'.Str::lower(Str::random(24)));
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        throw new InvalidWebhookException('The sandbox does not accept inbound webhooks.', 404);
    }

    public function healthCheck(): HealthCheckResult
    {
        return new HealthCheckResult(HealthCheckResult::HEALTHY, 'Sandbox simulator (test mode).');
    }
}

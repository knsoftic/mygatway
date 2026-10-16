<?php

namespace App\Jobs;

use App\Enums\Mode;
use App\Integrations\Data\ProviderWebhookData;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\GatewayManager;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ProviderWebhookLog;
use App\Services\Payments\PaymentService;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Applies a signature-verified provider callback. By default the state is also
 * confirmed with an authenticated provider status query before it is applied
 * (config gateway.payments.verify_callbacks_with_api).
 */
class ProcessProviderWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900, 1800];

    public function __construct(
        public readonly int $logId,
        public readonly string $providerCode,
        public readonly ProviderWebhookData $webhook,
    ) {
        $this->onQueue('provider-webhooks');
    }

    public function handle(GatewayManager $gateways, PaymentService $payments, PayoutService $payouts): void
    {
        $log = ProviderWebhookLog::query()->find($this->logId);
        if (! $log || $log->status === ProviderWebhookLog::PROCESSED) {
            return;
        }
        $log->increment('attempts');

        $confirmWithApi = (bool) config('gateway.payments.verify_callbacks_with_api', true);
        $gateway = $gateways->live($this->providerCode);

        try {
            if ($this->webhook->kind === ProviderWebhookData::KIND_PAYMENT) {
                $payment = Payment::query()
                    ->where('provider_order_id', $this->webhook->reference)
                    ->where('mode', Mode::Live->value)
                    ->whereHas('provider', fn ($q) => $q->where('code', $this->providerCode))
                    ->first();

                if (! $payment) {
                    $this->finish($log, ProviderWebhookLog::IGNORED, 'No live payment matches reference '.$this->webhook->reference);

                    return;
                }

                $data = ($confirmWithApi || $this->webhook->requiresStatusQuery) ? $gateway->fetchPayment($payment) : $this->webhook->payment;
                $payments->applyProviderData($payment, $data, 'webhook');
            } elseif ($this->webhook->kind === ProviderWebhookData::KIND_PAYOUT) {
                $payout = Payout::query()
                    ->where('provider_order_id', $this->webhook->reference)
                    ->where('mode', Mode::Live->value)
                    ->whereHas('provider', fn ($q) => $q->where('code', $this->providerCode))
                    ->first();

                if (! $payout) {
                    $this->finish($log, ProviderWebhookLog::IGNORED, 'No live payout matches reference '.$this->webhook->reference);

                    return;
                }

                $data = ($confirmWithApi || $this->webhook->requiresStatusQuery) ? $gateway->fetchPayout($payout) : $this->webhook->payout;
                $payouts->applyProviderData($payout, $data, 'webhook');
            } else {
                $this->finish($log, ProviderWebhookLog::IGNORED, 'Event type not used by the platform');

                return;
            }

            $this->finish($log, ProviderWebhookLog::PROCESSED);
        } catch (ProviderException $e) {
            $log->forceFill(['status' => ProviderWebhookLog::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            if ($e->retryable) {
                throw $e;
            }
        } catch (Throwable $e) {
            $log->forceFill(['status' => ProviderWebhookLog::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        }
    }

    private function finish(ProviderWebhookLog $log, string $status, ?string $note = null): void
    {
        $log->forceFill(['status' => $status, 'error' => $note, 'processed_at' => now()])->save();
    }
}

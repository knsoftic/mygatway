<?php

namespace App\Services\Risk;

use App\Models\LoginHistory;
use App\Models\Payout;
use App\Models\RiskEvent;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Rule-based suspicious activity detection. Events land in the admin risk queue;
 * nothing here blocks money movement on its own (approvals and limits do that).
 */
class RiskMonitor
{
    /** @param  array<string, mixed>  $details */
    public function record(string $type, string $severity, array $details = [], ?int $merchantId = null, ?int $userId = null, ?Model $subject = null): RiskEvent
    {
        Log::channel(config('logging.default'))->warning('Risk event', ['type' => $type, 'severity' => $severity, 'merchant_id' => $merchantId]);

        return RiskEvent::query()->create([
            'merchant_id' => $merchantId,
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'details' => $details,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    public function checkFailedLogins(string $email, ?string $ip): void
    {
        $window = (int) config('gateway.risk.failed_login_window_minutes', 15);
        $threshold = (int) config('gateway.risk.failed_login_threshold', 5);

        $failures = LoginHistory::query()
            ->where('email', $email)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($window))
            ->count();

        if ($failures === $threshold) {
            $user = User::query()->where('email', $email)->first();
            $this->record('auth.failed_login_burst', $user?->is_admin ? 'high' : 'medium', [
                'email' => $email,
                'ip' => $ip,
                'failures' => $failures,
                'window_minutes' => $window,
            ], $user?->merchant_id, $user?->id);
        }
    }

    public function checkPayout(Payout $payout): void
    {
        $threshold = (string) config('gateway.risk.large_payout_review_threshold', '10000');

        if (Decimal::gte($payout->amount, $threshold)) {
            $this->record('payout.large_amount', 'medium', [
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'payout' => $payout->public_id,
            ], $payout->merchant_id, $payout->requested_by_user_id, $payout);
        }

        $seenBefore = Payout::query()
            ->where('merchant_id', $payout->merchant_id)
            ->where('mode', $payout->mode)
            ->where('destination', $payout->destination)
            ->where('id', '<', $payout->id)
            ->whereIn('status', ['completed'])
            ->exists();

        if (! $seenBefore && $payout->mode->value === 'live') {
            $this->record('payout.new_destination', 'low', [
                'destination' => $payout->maskedDestination(),
                'payout' => $payout->public_id,
            ], $payout->merchant_id, $payout->requested_by_user_id, $payout);
        }
    }
}

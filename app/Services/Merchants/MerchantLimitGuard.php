<?php

namespace App\Services\Merchants;

use App\Enums\FeeDirection;
use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\MerchantLimit;
use App\Models\Payment;
use App\Models\Payout;
use App\Support\Decimal;

/**
 * Risk-based transaction limits configured per merchant, direction and currency (live mode only).
 */
class MerchantLimitGuard
{
    public function check(Merchant $merchant, FeeDirection $direction, string $currency, string $amount, Mode $mode): void
    {
        if ($mode !== Mode::Live) {
            return;
        }

        $limit = MerchantLimit::query()
            ->where('merchant_id', $merchant->id)
            ->where('direction', $direction->value)
            ->where('currency', strtoupper($currency))
            ->first();

        if (! $limit) {
            return;
        }

        if ($limit->max_per_transaction !== null && Decimal::gt($amount, (string) $limit->max_per_transaction)) {
            throw new GatewayException('limit_exceeded', "The amount exceeds your per-transaction limit of {$limit->max_per_transaction} {$currency}.", 422, [
                'limit' => 'max_per_transaction',
                'value' => $limit->max_per_transaction,
            ]);
        }

        if ($limit->max_daily_total !== null) {
            $todayTotal = $direction === FeeDirection::PayIn
                ? (string) Payment::query()
                    ->where('merchant_id', $merchant->id)->where('mode', $mode->value)->where('currency', strtoupper($currency))
                    ->where('created_at', '>=', now()->startOfDay())
                    ->whereNotIn('status', [PaymentStatus::Failed->value, PaymentStatus::Expired->value, PaymentStatus::Cancelled->value])
                    ->sum('amount')
                : (string) Payout::query()
                    ->where('merchant_id', $merchant->id)->where('mode', $mode->value)->where('currency', strtoupper($currency))
                    ->where('created_at', '>=', now()->startOfDay())
                    ->whereNotIn('status', [PayoutStatus::Failed->value, PayoutStatus::Rejected->value, PayoutStatus::Cancelled->value])
                    ->sum('amount');

            if (Decimal::gt(Decimal::add(Decimal::of($todayTotal ?: '0'), $amount), (string) $limit->max_daily_total)) {
                throw new GatewayException('limit_exceeded', "This request would exceed your daily limit of {$limit->max_daily_total} {$currency}.", 422, [
                    'limit' => 'max_daily_total',
                    'value' => $limit->max_daily_total,
                ]);
            }
        }
    }
}

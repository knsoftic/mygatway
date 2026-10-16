<?php

namespace App\Services\Reporting;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Payment;
use App\Models\Payout;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations for dashboards. Every figure is grouped by currency (never summed
 * across currencies) and computed from persisted, provider-verified records.
 */
class TransactionStats
{
    public const SETTLED = ['completed', 'underpaid', 'refund_pending', 'refunded'];

    public const OPEN = ['created', 'pending', 'processing', 'confirming', 'on_hold'];

    public const UNSUCCESSFUL = ['failed', 'expired', 'cancelled'];

    /**
     * @param  Builder<Payment>  $payments  Pre-filtered (merchant, mode, dates...).
     * @param  Builder<Payout>  $payouts
     * @return Collection<string, array<string, string|int>>
     */
    public function perCurrency(Builder $payments, Builder $payouts): Collection
    {
        $in = (clone $payments)
            ->selectRaw('currency')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::SETTLED).') THEN received_amount ELSE 0 END) AS volume')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::SETTLED).') THEN service_fee ELSE 0 END) AS fees')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::SETTLED).') THEN COALESCE(provider_fee, 0) ELSE 0 END) AS provider_fees')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::SETTLED).') THEN 1 ELSE 0 END) AS succeeded')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::OPEN).') THEN 1 ELSE 0 END) AS pending')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->list(self::UNSUCCESSFUL).') THEN 1 ELSE 0 END) AS failed')
            ->groupBy('currency')->get()->keyBy('currency');

        $out = (clone $payouts)
            ->selectRaw('currency')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS volume")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN service_fee + network_fee ELSE 0 END) AS fees")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN COALESCE(provider_fee, 0) ELSE 0 END) AS provider_fees")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS succeeded")
            ->selectRaw("SUM(CASE WHEN status IN ('requested','under_review','approved','processing') THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN status IN ('failed','rejected','cancelled') THEN 1 ELSE 0 END) AS failed")
            ->groupBy('currency')->get()->keyBy('currency');

        $currencies = $in->keys()->merge($out->keys())->unique()->sort()->values();

        return $currencies->mapWithKeys(fn (string $c) => [$c => [
            'payin_volume' => Decimal::of((string) ($in[$c]->volume ?? '0')),
            'payin_fees' => Decimal::of((string) ($in[$c]->fees ?? '0')),
            'payin_provider_fees' => Decimal::of((string) ($in[$c]->provider_fees ?? '0')),
            'payout_volume' => Decimal::of((string) ($out[$c]->volume ?? '0')),
            'payout_fees' => Decimal::of((string) ($out[$c]->fees ?? '0')),
            'payout_provider_fees' => Decimal::of((string) ($out[$c]->provider_fees ?? '0')),
            'succeeded' => (int) ($in[$c]->succeeded ?? 0) + (int) ($out[$c]->succeeded ?? 0),
            'pending' => (int) ($in[$c]->pending ?? 0) + (int) ($out[$c]->pending ?? 0),
            'failed' => (int) ($in[$c]->failed ?? 0) + (int) ($out[$c]->failed ?? 0),
        ]]);
    }

    /**
     * Volume series for one currency.
     *
     * @param  'day'|'week'|'month'  $bucket
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<float>}>, unit: string}
     */
    public function series(Builder $payments, Builder $payouts, string $currency, string $bucket, int $points): array
    {
        [$sqlFormat, $phpFormat, $step, $labelFormat] = match ($bucket) {
            'week' => ['%x-W%v', 'o-\WW', 'week', 'M j'],
            'month' => ['%Y-%m', 'Y-m', 'month', 'M Y'],
            default => ['%Y-%m-%d', 'Y-m-d', 'day', 'M j'],
        };

        $start = Carbon::now()->startOf($step)->sub($step, $points - 1);

        $in = (clone $payments)->where('currency', $currency)->whereIn('status', self::SETTLED)->where('completed_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(completed_at, '{$sqlFormat}') AS b, SUM(received_amount) AS v")->groupBy('b')->pluck('v', 'b');
        $out = (clone $payouts)->where('currency', $currency)->where('status', PayoutStatus::Completed->value)->where('completed_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(completed_at, '{$sqlFormat}') AS b, SUM(amount) AS v")->groupBy('b')->pluck('v', 'b');

        $labels = $inData = $outData = [];
        for ($i = 0; $i < $points; $i++) {
            $d = $start->copy()->add($step, $i);
            $key = $d->format($phpFormat);
            $labels[] = $d->format($labelFormat);
            // Chart values only (display); all accounting uses decimal strings.
            $inData[] = round((float) ($in[$key] ?? 0), 2);
            $outData[] = round((float) ($out[$key] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [['label' => 'Pay In', 'data' => $inData], ['label' => 'Pay Out', 'data' => $outData]],
            'unit' => $currency,
        ];
    }

    /** @param  list<string>  $values */
    private function list(array $values): string
    {
        return implode(',', array_map(fn ($v) => "'".$v."'", $values));
    }

    /** @return list<string> */
    public static function statusesFor(string $group): array
    {
        return match ($group) {
            'settled' => self::SETTLED,
            'open' => self::OPEN,
            default => self::UNSUCCESSFUL,
        };
    }

    public static function paymentStatusValid(?string $status): bool
    {
        return $status !== null && PaymentStatus::tryFrom($status) !== null;
    }
}

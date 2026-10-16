<?php

namespace App\Services\Fees;

use App\Enums\FeeDirection;
use App\Models\FeeRule;
use App\Models\TransactionFee;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Server-side fee engine. Merchants can never supply or influence a fee amount.
 *
 * fee = amount * percentage / 100 + fixed, then clamped to [min_fee, max_fee].
 * Pay In fees are capped at the received amount so the merchant net is never negative.
 *
 * Rule selection: among active rules effective at the given time that match the
 * direction and whose merchant/provider/currency are either NULL (wildcard) or equal
 * to the transaction's, the most specific wins (merchant > currency > provider);
 * ties go to the most recent effective_from.
 */
class FeeCalculator
{
    public function resolveRule(FeeDirection $direction, int $merchantId, int $providerId, string $currency, ?Carbon $at = null): ?FeeRule
    {
        $at ??= now();

        return FeeRule::query()
            ->where('direction', $direction->value)
            ->effectiveAt($at)
            ->where(fn (Builder $q) => $q->whereNull('merchant_id')->orWhere('merchant_id', $merchantId))
            ->where(fn (Builder $q) => $q->whereNull('provider_id')->orWhere('provider_id', $providerId))
            ->where(fn (Builder $q) => $q->whereNull('currency')->orWhere('currency', strtoupper($currency)))
            ->get()
            ->sort(function (FeeRule $a, FeeRule $b) {
                return [$b->specificity(), $b->effective_from->getTimestamp(), $b->id]
                    <=> [$a->specificity(), $a->effective_from->getTimestamp(), $a->id];
            })
            ->first();
    }

    public function quote(FeeDirection $direction, int $merchantId, int $providerId, string $currency, string $amount, ?Carbon $at = null): FeeQuote
    {
        $rule = $this->resolveRule($direction, $merchantId, $providerId, $currency, $at);

        return $this->compute(
            baseAmount: $amount,
            currency: strtoupper($currency),
            percentage: $rule ? (string) $rule->percentage : '0',
            fixedAmount: $rule ? (string) $rule->fixed_amount : '0',
            minFee: $rule?->min_fee,
            maxFee: $rule?->max_fee,
            ruleId: $rule?->id,
            snapshot: $rule?->snapshot(),
            capAtBase: $direction === FeeDirection::PayIn,
        );
    }

    /**
     * Recompute a fee for a new base amount using the parameters frozen in an earlier quote.
     */
    public function requote(TransactionFee|FeeQuote $original, string $newBaseAmount, bool $capAtBase = true): FeeQuote
    {
        if ($original instanceof TransactionFee) {
            return $this->compute(
                baseAmount: $newBaseAmount,
                currency: $original->currency,
                percentage: (string) $original->percentage,
                fixedAmount: (string) $original->fixed_amount,
                minFee: $original->min_fee,
                maxFee: $original->max_fee,
                ruleId: $original->fee_rule_id,
                snapshot: $original->rule_snapshot,
                capAtBase: $capAtBase,
            );
        }

        return $this->compute($newBaseAmount, $original->currency, $original->percentage, $original->fixedAmount,
            $original->minFee, $original->maxFee, $original->ruleId, $original->ruleSnapshot, $capAtBase);
    }

    public function compute(
        string $baseAmount,
        string $currency,
        string $percentage,
        string $fixedAmount,
        ?string $minFee,
        ?string $maxFee,
        ?int $ruleId = null,
        ?array $snapshot = null,
        bool $capAtBase = true,
    ): FeeQuote {
        $base = Decimal::of($baseAmount);
        $fee = Decimal::add(Decimal::percentOf($base, $percentage), Decimal::of($fixedAmount));

        if ($minFee !== null && Decimal::lt($fee, $minFee)) {
            $fee = Decimal::of($minFee);
        }
        if ($maxFee !== null && Decimal::gt($fee, $maxFee)) {
            $fee = Decimal::of($maxFee);
        }
        if ($capAtBase && Decimal::gt($fee, $base)) {
            $fee = $base;
        }
        if (Decimal::isNegative($fee)) {
            $fee = Decimal::zero();
        }

        return new FeeQuote(
            amount: $fee,
            baseAmount: $base,
            currency: $currency,
            ruleId: $ruleId,
            percentage: $percentage,
            fixedAmount: Decimal::of($fixedAmount),
            minFee: $minFee !== null ? Decimal::of($minFee) : null,
            maxFee: $maxFee !== null ? Decimal::of($maxFee) : null,
            ruleSnapshot: $snapshot,
        );
    }

    /** Persist an immutable fee record (idempotent per feeable/kind/stage). */
    public function record(Model $feeable, string $kind, string $stage, FeeQuote $quote): TransactionFee
    {
        return TransactionFee::query()->firstOrCreate(
            [
                'feeable_type' => $feeable->getMorphClass(),
                'feeable_id' => $feeable->getKey(),
                'kind' => $kind,
                'stage' => $stage,
            ],
            [
                'fee_rule_id' => $quote->ruleId,
                'currency' => $quote->currency,
                'base_amount' => $quote->baseAmount,
                'percentage' => $quote->percentage,
                'fixed_amount' => $quote->fixedAmount,
                'min_fee' => $quote->minFee,
                'max_fee' => $quote->maxFee,
                'amount' => $quote->amount,
                'rule_snapshot' => $quote->ruleSnapshot,
                'created_at' => now(),
            ],
        );
    }
}

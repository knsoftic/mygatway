<?php

namespace App\Services\Fees;

/**
 * Result of a fee calculation plus the exact parameters used, so the same fee can be
 * recomputed later (e.g. on the received amount) even if the rule has since changed.
 */
final readonly class FeeQuote
{
    /** @param  array<string, mixed>|null  $ruleSnapshot */
    public function __construct(
        public string $amount,
        public string $baseAmount,
        public string $currency,
        public ?int $ruleId,
        public string $percentage,
        public string $fixedAmount,
        public ?string $minFee,
        public ?string $maxFee,
        public ?array $ruleSnapshot,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'base_amount' => $this->baseAmount,
            'currency' => $this->currency,
            'rule_id' => $this->ruleId,
            'percentage' => $this->percentage,
            'fixed_amount' => $this->fixedAmount,
            'min_fee' => $this->minFee,
            'max_fee' => $this->maxFee,
        ];
    }
}

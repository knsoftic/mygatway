<?php

namespace App\Http\Resources;

use App\Enums\LedgerAccountType;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A merchant-facing view of a ledger journal: only the entries on the merchant's
 * own accounts are exposed (platform and provider accounts stay internal).
 *
 * @mixin Transaction
 */
class LedgerTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entries = $this->entries
            ->filter(fn (LedgerEntry $e) => $e->account?->merchant_id === $this->merchant_id && $e->account->type->isMerchantAccount())
            ->map(fn (LedgerEntry $e) => [
                'balance' => $e->account->type === LedgerAccountType::MerchantAvailable ? 'available' : 'reserved',
                'direction' => $e->direction->value === 'credit' ? 'in' : 'out',
                'amount' => $e->amount,
                'balance_after' => $e->balance_after,
            ])->values();

        return [
            'id' => $this->public_id,
            'object' => 'transaction',
            'mode' => $this->mode->value,
            'type' => $this->type,
            'currency' => $this->currency,
            'description' => $this->description,
            'source' => $this->reference ? [
                'object' => match (true) {
                    $this->reference instanceof \App\Models\Payment => 'payment',
                    $this->reference instanceof \App\Models\Payout => 'payout',
                    $this->reference instanceof \App\Models\Refund => 'refund',
                    default => 'transaction',
                },
                'id' => $this->reference->public_id ?? null,
            ] : null,
            'entries' => $entries,
            'posted_at' => $this->posted_at?->toIso8601String(),
        ];
    }
}

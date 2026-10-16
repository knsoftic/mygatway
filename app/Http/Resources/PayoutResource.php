<?php

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payout */
class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'object' => 'payout',
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'provider' => $this->provider?->code,
            'reference' => $this->merchant_reference,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'network' => $this->network,
            'destination' => [
                'type' => $this->destination_type->value,
                'value' => $this->destination,
                'memo' => $this->destination_memo,
            ],
            'service_fee' => $this->service_fee,
            'network_fee' => $this->network_fee,
            'total_debit' => $this->total_debit,
            'tx_hash' => $this->tx_hash,
            'failure_reason' => $this->failure_reason,
            'rejection_reason' => $this->rejection_reason,
            'metadata' => $this->metadata ?? (object) [],
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

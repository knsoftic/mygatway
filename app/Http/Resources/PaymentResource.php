<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'object' => 'payment',
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'provider' => $this->provider?->code,
            'reference' => $this->merchant_reference,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'network' => $this->network,
            'received_amount' => $this->received_amount,
            'received_currency' => $this->received_currency,
            // Quoted at creation; replaced by the final fee (on the received amount) at settlement.
            'service_fee' => $this->service_fee,
            'net_amount' => $this->merchant_net_amount,
            'checkout_url' => $this->checkout_url,
            'pay_address' => $this->pay_address,
            'payer_address' => $this->payer_address,
            'tx_hash' => $this->tx_hash,
            'customer_email' => $this->customer_email,
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'metadata' => $this->metadata ?? (object) [],
            'callback_verified' => $this->callback_verified,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

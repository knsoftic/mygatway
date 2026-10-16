<?php

namespace App\Http\Requests\Api;

use App\Enums\PayoutDestinationType;
use App\Rules\PayoutDestination;
use Illuminate\Validation\Rule;

class CreatePayoutRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_numeric($this->input('amount')) && ! is_string($this->input('amount'))) {
            $this->merge(['amount' => (string) $this->input('amount')]);
        }
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => $this->amountRules(),
            'currency' => ['required', 'string', 'regex:/^[A-Z0-9]{2,16}$/'],
            'network' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{1,32}$/'],
            'provider' => ['nullable', 'string', Rule::in(['cryptomus', 'binance_pay'])],
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{1,64}$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'destination' => ['required', 'array:type,value,memo'],
            'destination.type' => ['required', Rule::enum(PayoutDestinationType::class)],
            'destination.value' => ['required', 'string', 'max:191', new PayoutDestination((string) $this->input('destination.type'))],
            'destination.memo' => ['nullable', 'string', 'max:64'],
            ...$this->metadataRules(),
            ...$this->prohibitedFeeFields(),
        ];
    }

    /** @return array<string, mixed> Flattened input for PayoutService::request(). */
    public function payoutInput(): array
    {
        $v = $this->validated();

        return [
            'amount' => $v['amount'],
            'currency' => $v['currency'],
            'network' => $v['network'] ?? null,
            'provider' => $v['provider'] ?? null,
            'reference' => $v['reference'],
            'description' => $v['description'] ?? null,
            'destination_type' => $v['destination']['type'],
            'destination' => $v['destination']['value'],
            'memo' => $v['destination']['memo'] ?? null,
            'metadata' => $v['metadata'] ?? null,
        ];
    }
}

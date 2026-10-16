<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class CreatePaymentRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_numeric($this->input('amount')) && ! is_string($this->input('amount'))) {
            // Accept JSON numbers but validate them as strings (no float maths afterwards).
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
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'success_url' => ['nullable', 'url:https,http', 'max:255'],
            'cancel_url' => ['nullable', 'url:https,http', 'max:255'],
            'lifetime' => ['nullable', 'integer', 'between:'.config('gateway.payments.min_lifetime').','.config('gateway.payments.max_lifetime')],
            ...$this->metadataRules(),
            ...$this->prohibitedFeeFields(),
        ];
    }
}

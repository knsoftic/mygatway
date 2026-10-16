<?php

namespace App\Http\Requests\Merchant;

use App\Enums\Mode;
use App\Enums\PayoutDestinationType;
use App\Rules\PayoutDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->merchant !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency')),
            'reference' => $this->input('reference') ?: 'dash-'.Str::lower(Str::random(12)),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['cryptomus', 'binance_pay'])],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,17})(?:\.\d{1,8})?$/', 'not_regex:/^0(?:\.0+)?$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z0-9]{2,16}$/'],
            'network' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{1,32}$/'],
            'destination_type' => ['required', Rule::enum(PayoutDestinationType::class)],
            'destination' => ['required', 'string', 'max:191', new PayoutDestination((string) $this->input('destination_type'))],
            'memo' => ['nullable', 'string', 'max:64'],
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{1,64}$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'confirm_destination' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $mode = $this->attributes->get('dashboard_mode');
            if ($mode === Mode::Live && ! $this->user()->hasTwoFactorEnabled()) {
                $v->errors()->add('destination', 'Enable two-factor authentication before requesting live payouts.');
            }
        }];
    }

    public function messages(): array
    {
        return ['confirm_destination.accepted' => 'Please confirm the destination details. Crypto transfers cannot be reversed.'];
    }

    /** @return array<string, mixed> */
    public function payoutInput(): array
    {
        return collect($this->validated())->except('confirm_destination')->all();
    }
}

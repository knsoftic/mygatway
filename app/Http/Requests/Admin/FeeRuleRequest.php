<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fees.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->filled('currency') ? strtoupper((string) $this->input('currency')) : null,
            'fixed_amount' => $this->filled('fixed_amount') ? $this->input('fixed_amount') : '0',
            'percentage' => $this->filled('percentage') ? $this->input('percentage') : '0',
        ]);
    }

    public function rules(): array
    {
        $money = ['nullable', 'string', 'regex:/^\d{1,18}(\.\d{1,8})?$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'in:payin,payout'],
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'provider_id' => ['nullable', 'integer', 'exists:payment_providers,id'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Z0-9]{2,16}$/'],
            'percentage' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'fixed_amount' => $money,
            'min_fee' => $money,
            'max_fee' => $money,
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (bccomp((string) $this->input('percentage', '0'), '100', 4) === 1) {
                $v->errors()->add('percentage', 'Percentage cannot exceed 100.');
            }

            // Fixed amounts are in transaction currency units, so they only make sense per currency.
            $needsCurrency = bccomp((string) $this->input('fixed_amount', '0'), '0', 8) === 1
                || $this->filled('min_fee') || $this->filled('max_fee');
            if ($needsCurrency && ! $this->filled('currency')) {
                $v->errors()->add('currency', 'Fixed, minimum and maximum fees require a specific currency.');
            }

            if ($this->filled('min_fee') && $this->filled('max_fee') && bccomp((string) $this->input('min_fee'), (string) $this->input('max_fee'), 8) === 1) {
                $v->errors()->add('max_fee', 'Maximum fee must be greater than or equal to the minimum fee.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function ruleData(): array
    {
        $d = $this->validated();
        $d['min_fee'] = $d['min_fee'] ?? null ?: null;
        $d['max_fee'] = $d['max_fee'] ?? null ?: null;
        $d['is_active'] = $this->boolean('is_active');

        return $d;
    }
}

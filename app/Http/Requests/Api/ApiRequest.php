<?php

namespace App\Http\Requests\Api;

use App\Enums\Mode;
use App\Models\Merchant;
use App\Models\MerchantApiKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base form request for the merchant API. Authorisation (API key + scope) is enforced
 * by middleware before validation runs.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('merchant');
    }

    public function merchant(): Merchant
    {
        return $this->attributes->get('merchant');
    }

    public function apiKey(): MerchantApiKey
    {
        return $this->attributes->get('api_key');
    }

    public function mode(): Mode
    {
        return $this->attributes->get('mode');
    }

    /** Shared rule for decimal amount strings with up to 8 decimals. */
    protected function amountRules(): array
    {
        return ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,17})(?:\.\d{1,8})?$/', 'not_regex:/^0(?:\.0+)?$/'];
    }

    /** Merchant metadata: flat object, max 20 keys, scalar values up to 500 characters. */
    protected function metadataRules(): array
    {
        return [
            'metadata' => ['nullable', 'array', 'max:'.config('gateway.api.max_metadata_keys', 20)],
            'metadata.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** Fee fields are always calculated server-side; sending them is an error. */
    protected function prohibitedFeeFields(): array
    {
        return [
            'fee' => ['prohibited'],
            'service_fee' => ['prohibited'],
            'network_fee' => ['prohibited'],
            'net_amount' => ['prohibited'],
            'total_debit' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'The amount must be a positive decimal string with at most 8 decimal places (e.g. "125.50").',
            'amount.not_regex' => 'The amount must be greater than zero.',
            '*.prohibited' => 'Fees are calculated by the platform and cannot be supplied.',
        ];
    }
}

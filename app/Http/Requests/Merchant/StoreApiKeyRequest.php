<?php

namespace App\Http\Requests\Merchant;

use App\Models\MerchantApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\IpUtils;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MerchantApiKey::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(config('gateway.api.scopes'))],
            'ip_allowlist' => ['nullable', 'string', 'max:2000'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'between:10,'.config('gateway.api.max_rate_limit_per_minute')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->ipList() ?? [] as $entry) {
                [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);
                $validIp = filter_var($ip, FILTER_VALIDATE_IP) !== false;
                $validMask = $mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($ip, ':') ? 128 : 32));
                if (! $validIp || ! $validMask || ! IpUtils::checkIp($ip, $entry)) {
                    $validator->errors()->add('ip_allowlist', "\"{$entry}\" is not a valid IP address or CIDR range.");
                }
            }

            $merchant = $this->user()->merchant;
            if ($this->input('mode') === 'live') {
                if (! $merchant->isLiveEnabled()) {
                    $validator->errors()->add('mode', 'Live keys are available after your business verification is approved.');
                }
                if (! $this->user()->hasTwoFactorEnabled()) {
                    $validator->errors()->add('mode', 'Enable two-factor authentication before creating live keys.');
                }
            }
        }];
    }

    /** @return list<string>|null */
    public function ipList(): ?array
    {
        $raw = trim((string) $this->input('ip_allowlist', ''));
        if ($raw === '') {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw)))));
    }
}

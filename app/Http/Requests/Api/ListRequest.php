<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class ListRequest extends ApiRequest
{
    /** @var list<string> */
    protected array $statuses = [];

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in($this->allowedStatuses())],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/'],
            'reference' => ['nullable', 'string', 'max:64'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }

    /** @return list<string> */
    protected function allowedStatuses(): array
    {
        return match (true) {
            $this->routeIs('api.v1.payments.*') => \App\Enums\PaymentStatus::values(),
            $this->routeIs('api.v1.payouts.*') => \App\Enums\PayoutStatus::values(),
            default => [],
        };
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 25);
    }
}

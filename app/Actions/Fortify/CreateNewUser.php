<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Merchants\MerchantOnboardingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly MerchantOnboardingService $onboarding) {}

    /**
     * Register a merchant owner and their (pending) merchant account.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class)],
            'business_name' => ['required', 'string', 'max:255'],
            'business_website' => ['nullable', 'url:https,http', 'max:255'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $this->onboarding->register($user, $input['business_name'], $input['business_website'] ?? null);

            return $user->refresh();
        });
    }
}

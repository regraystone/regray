<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\ClientRegistrationService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // No account without the terms; promotional emails are optional
            // and off unless the box was ticked.
            'terms' => ['accepted'],
            'marketing_emails' => ['sometimes', 'boolean'],
        ], [
            'terms.accepted' => __('Agree to the Terms & Conditions to create an account.'),
        ])->validate();

        return app(ClientRegistrationService::class)->register(
            $input['name'],
            $input['email'],
            $input['password'],
            (bool) ($validated['marketing_emails'] ?? false),
            request(),
        );
    }
}

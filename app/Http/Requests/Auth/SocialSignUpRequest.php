<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Finish signing up with Google: the name to use, and the same two boxes as Sign Up. */
class SocialSignUpRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'terms' => ['accepted'],
            'marketing_emails' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terms.accepted' => __('Agree to the Terms & Conditions to create an account.'),
        ];
    }
}

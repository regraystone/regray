<?php

namespace App\Http\Requests\Inquiry;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A direct (walk-in) client as staff take them down: their full name and
 * phone number, and their email when they give one. Nothing else: where the
 * work goes is asked on each inquiry, with the address picker.
 */
class StoreDirectClientRequest extends FormRequest
{
    /** A phone number as it is written: digits, with a leading +, spaces, dashes, dots or brackets. */
    public const PHONE_PATTERN = '/^\+?[0-9\s\-().]{7,20}$/';

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
            'email' => ['nullable', 'string', 'max:255', 'email'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fullName.required' => "Enter the client's full name.",
            'fullName.max' => 'Keep the name to 150 characters or fewer.',
            'phone.required' => "Enter the client's phone number.",
            'phone.regex' => 'Enter a valid phone number.',
            'phone.max' => 'Enter a valid phone number.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'Enter a valid email address.',
        ];
    }

    /** @return array{full_name: string, phone: string, email: ?string} */
    public function clientInput(): array
    {
        $email = $this->validated('email');

        return [
            'full_name' => (string) $this->validated('fullName'),
            'phone' => (string) $this->validated('phone'),
            'email' => is_string($email) && trim($email) !== '' ? $email : null,
        ];
    }
}

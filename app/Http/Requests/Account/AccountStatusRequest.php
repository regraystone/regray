<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/** The row's switch: turn the account on (`active: true`) or off. */
class AccountStatusRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}

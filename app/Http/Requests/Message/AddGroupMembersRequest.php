<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class AddGroupMembersRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One accounting user and one logistics user, always together: a group
     * takes exactly one of each.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'accountingId' => ['required', 'integer', 'exists:users,id'],
            'logisticsId' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accountingId.required' => 'Choose one person from accounting.',
            'logisticsId.required' => 'Choose one person from logistics.',
        ];
    }

    public function accountingId(): int
    {
        return (int) $this->validated('accountingId');
    }

    public function logisticsId(): int
    {
        return (int) $this->validated('logisticsId');
    }
}

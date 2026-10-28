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

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'userIds' => ['required', 'array', 'min:1', 'max:20'],
            'userIds.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'userIds.required' => 'Choose at least one person.',
            'userIds.min' => 'Choose at least one person.',
        ];
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_values(array_map(fn ($id): int => (int) $id, $this->validated('userIds')));
    }
}

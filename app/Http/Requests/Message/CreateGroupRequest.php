<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'staffIds' => ['required', 'array', 'min:1', 'max:50'],
            'staffIds.*' => ['integer', 'distinct', 'exists:users,id'],
            'clientId' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the group a name.',
            'staffIds.required' => 'Add at least one staff member.',
            'staffIds.min' => 'Add at least one staff member.',
            'clientId.required' => 'Choose one client.',
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    /** @return list<int> */
    public function staffIds(): array
    {
        return array_values(array_map(fn ($id): int => (int) $id, $this->validated('staffIds')));
    }

    public function clientId(): int
    {
        return (int) $this->validated('clientId');
    }
}

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
            'staffIds' => ['required', 'array', 'size:2'],
            'staffIds.*' => ['integer', 'distinct', 'exists:users,id'],
            'clientId' => ['required', 'integer', 'exists:users,id'],
            'inquiryId' => ['required', 'integer', 'exists:inquiries,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the group a name.',
            'staffIds.required' => 'Choose one sales member and one technical member.',
            'staffIds.size' => 'Choose one sales member and one technical member.',
            'clientId.required' => 'Choose one client.',
            'inquiryId.required' => 'Choose the inquiry this group is for.',
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

    public function inquiryId(): int
    {
        return (int) $this->validated('inquiryId');
    }
}

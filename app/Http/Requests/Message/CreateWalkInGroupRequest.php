<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A walk-in inquiry's group chat: its name, its two staff and the inquiry.
 * No client: the account holder is found from the walk-in's contacts.
 */
class CreateWalkInGroupRequest extends FormRequest
{
    /** The route middleware decides access; the service, which inquiry. */
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
            'staffIds.*' => ['integer', 'distinct'],
            'inquiryId' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the group a name.',
            'staffIds.required' => 'Choose one technical member.',
            'staffIds.size' => 'Choose one technical member.',
            'inquiryId.required' => 'Choose the walk-in inquiry this group is for.',
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

    public function inquiryId(): int
    {
        return (int) $this->validated('inquiryId');
    }
}

<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
{
    /** The route middleware and LeadPolicy decide access. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in([LeadStatus::Won->value, LeadStatus::Lost->value])],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Add a note explaining this change.',
            'note.max' => 'Keep the note to 500 characters.',
        ];
    }

    public function to(): LeadStatus
    {
        return LeadStatus::from((string) $this->validated('to'));
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}

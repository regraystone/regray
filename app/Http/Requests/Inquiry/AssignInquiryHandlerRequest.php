<?php

namespace App\Http\Requests\Inquiry;

use Illuminate\Foundation\Http\FormRequest;

/** The staff member to add to a walk-in inquiry (`userId`). */
class AssignInquiryHandlerRequest extends FormRequest
{
    /** The route middleware and InquiryPolicy::assign() decide access. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'userId' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'userId.required' => 'Choose who to assign.',
        ];
    }

    public function userId(): int
    {
        return (int) $this->validated('userId');
    }
}

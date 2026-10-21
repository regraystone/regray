<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class ReassignConversationRequest extends FormRequest
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
            'userId' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function userId(): int
    {
        return (int) $this->validated('userId');
    }
}

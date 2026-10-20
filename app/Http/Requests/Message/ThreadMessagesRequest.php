<?php

namespace App\Http\Requests\Message;

use App\Services\Message\ConversationService;
use Illuminate\Foundation\Http\FormRequest;

class ThreadMessagesRequest extends FormRequest
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
            'before' => ['nullable', 'integer', 'min:1'],
            'after' => ['nullable', 'integer', 'min:0', 'prohibits:before'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ConversationService::MAX_THREAD_PAGE],
        ];
    }

    public function beforeId(): ?int
    {
        return $this->filled('before') ? (int) $this->validated('before') : null;
    }

    public function afterId(): ?int
    {
        return $this->filled('after') ? (int) $this->validated('after') : null;
    }

    public function limitValue(): ?int
    {
        return $this->filled('limit') ? (int) $this->validated('limit') : null;
    }
}

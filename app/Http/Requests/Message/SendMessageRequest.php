<?php

namespace App\Http\Requests\Message;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public const MAX_BODY = 5000;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'replyToId' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
            'clientKey' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a message first.',
            'body.max' => 'Keep a message to '.self::MAX_BODY.' characters or fewer.',
            'replyToId.exists' => 'That message is not in this conversation.',
        ];
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }

    public function replyToId(): ?int
    {
        return $this->filled('replyToId') ? (int) $this->validated('replyToId') : null;
    }

    public function clientKey(): string
    {
        return strtolower((string) $this->validated('clientKey'));
    }
}

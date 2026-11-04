<?php

namespace App\Http\Requests\Message;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Moves the read pointer or the delivered one; the route says which. */
class MovePointerRequest extends FormRequest
{
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
            $this->field() => [
                'required',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
        ];
    }

    public function messageId(): int
    {
        return (int) $this->validated($this->field());
    }

    public function isRead(): bool
    {
        return $this->routeIs('inbox.read');
    }

    private function field(): string
    {
        return $this->isRead() ? 'lastReadMessageId' : 'lastDeliveredMessageId';
    }
}

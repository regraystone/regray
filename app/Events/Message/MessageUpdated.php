<?php

namespace App\Events\Message;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A text message was edited or removed. Announced by id only, as
 * MessageCreated is, so each open thread fetches the edit, or the removed
 * message's tombstone, under the conversation's policy
 * (ThreadController::message()). Rescued when Reverb is down, as
 * MessageCreated is.
 */
class MessageUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public Conversation $conversation,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return $this->message->trashed() ? 'message.removed' : 'message.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['messageId' => $this->message->id];
    }
}

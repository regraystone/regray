<?php

namespace App\Events\Message;

use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A text message was edited or removed. A removed one travels as its tombstone.
 * Rescued when Reverb is down, as MessageCreated is.
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
        return ['message' => (new MessageResource($this->message, $this->conversation))->resolve(request())];
    }
}

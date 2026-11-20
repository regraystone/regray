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
 * A message landed in a conversation. Sent now rather than queued, so how fast
 * it arrives does not depend on the queue.
 *
 * Rescued rather than thrown when Reverb is down: the message is saved either
 * way, and the reader's catch-up on reconnect brings it to the others. Like
 * every broadcast-now event here, a failure is reported, never answered as a
 * 500 to the person who sent it.
 *
 * Announced by id only, whatever kind it is: each open thread fetches it over
 * HTTP, under the conversation's policy (ThreadController::messages()). A
 * channel is authorized once, when it is joined, and a connection opened then
 * would keep receiving whatever is broadcast on it after its owner lost
 * access (a reassignment, a claim, a deactivated account). With no content on
 * the channel, the fetch is where access is checked, every time. A card's
 * content also depends on who is reading it.
 */
class MessageCreated implements ShouldBroadcastNow, ShouldRescue
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
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['messageId' => $this->message->id, 'needsFetch' => true];
    }
}

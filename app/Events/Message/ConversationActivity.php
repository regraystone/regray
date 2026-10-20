<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed on a conversation's list row: a new message, an unread
 * count, an assignment. Each listed user's inbox and, for an unassigned Regray
 * Team thread, the support pool are told to refresh the row. Queued: it is a
 * list-level nudge, not the message itself.
 */
class ConversationActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        public int $conversationId,
        public array $userIds,
        public bool $toSupportPool = false,
        public string $reason = 'message',
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = array_map(fn (int $id): PrivateChannel => new PrivateChannel('user.'.$id), array_values(array_unique($this->userIds)));

        if ($this->toSupportPool) {
            $channels[] = new PrivateChannel('support.pool');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.activity';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversationId' => $this->conversationId, 'reason' => $this->reason];
    }
}

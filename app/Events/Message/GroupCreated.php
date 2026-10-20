<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** A group chat was created: each member's list gains it. Queued. */
class GroupCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<int>  $memberIds
     */
    public function __construct(
        public int $conversationId,
        public array $memberIds,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id): PrivateChannel => new PrivateChannel('user.'.$id), $this->memberIds);
    }

    public function broadcastAs(): string
    {
        return 'conversation.added';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversationId' => $this->conversationId];
    }
}

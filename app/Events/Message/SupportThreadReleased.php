<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Regray Team thread went back to the pool: it returns to every pool
 * member's list, with the assignment card on top.
 */
class SupportThreadReleased implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversationId),
            new PrivateChannel('support.pool'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.released';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversationId' => $this->conversationId];
    }
}

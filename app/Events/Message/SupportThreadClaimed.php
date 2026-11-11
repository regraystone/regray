<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Regray Team thread now belongs, for good, to the staff member who claimed
 * it.
 *
 * Goes to the thread and to the whole support pool: every pool member who is
 * not the assignee drops the thread from their list, and closes it if it is
 * open. Rescued when Reverb is down, as MessageCreated is.
 */
class SupportThreadClaimed implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{id: int, name: string}  $assignee
     */
    public function __construct(
        public int $conversationId,
        public array $assignee,
        public string $event = 'assigned',
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
        return 'support.claimed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'assignee' => $this->assignee,
            'event' => $this->event,
        ];
    }
}

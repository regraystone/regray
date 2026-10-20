<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member's read or delivered pointer moved, for the receipts under the latest
 * message. Only members who take part are announced, never pool viewers.
 */
class PointerMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public string $userName,
        public ?int $lastReadMessageId,
        public ?int $lastDeliveredMessageId,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'pointer.moved';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'name' => $this->userName,
            'lastReadMessageId' => $this->lastReadMessageId,
            'lastDeliveredMessageId' => $this->lastDeliveredMessageId,
        ];
    }
}

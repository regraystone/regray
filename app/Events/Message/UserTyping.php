<?php

namespace App\Events\Message;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "{name} is typing…". Announced by the server for a user it has checked may
 * write in the thread now, with the id and name of the signed-in account, never
 * what the browser says. Browsers cannot whisper on the channel themselves
 * (Reverb's `accept_client_events_from` is `none`). Rescued when Reverb is
 * down, as MessageCreated is.
 */
class UserTyping implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public string $userName,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /** @return array{userId: int, name: string} */
    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'name' => $this->userName,
        ];
    }
}

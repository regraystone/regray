<?php

namespace App\Events\Notification;

use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new line in the recipient's bell, pushed on their own `user.{id}` channel
 * with their unread count, so the badge moves without a reload. Sent once the
 * notification is committed, and rescued, so a Reverb outage never fails the
 * work that sent it.
 */
class NotificationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public UserNotification $notification,
        public int $unreadCount,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'notification' => NotificationResource::make($this->notification)->resolve(),
            'unreadCount' => $this->unreadCount,
        ];
    }
}

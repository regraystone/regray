<?php

namespace App\Jobs;

use App\Services\Notification\MessageNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one message notification and its email when its timer is due,
 * dispatched with that delay by MessageNotificationService::messageSent().
 *
 * Safe to run twice, early or after the recipient opened the thread: delivery
 * checks the timer under its row lock and sends nothing that is not due, has
 * gone, or was cancelled. One that runs early (a queue that ignores delays)
 * sends nothing, and `notifications:deliver-due` sends it on time instead.
 */
class DeliverMessageNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $pendingId,
    ) {}

    public function handle(MessageNotificationService $notifications): void
    {
        $notifications->deliver($this->pendingId);
    }
}

<?php

namespace App\Listeners;

use App\Events\ScheduleBooked;
use App\Events\ScheduleChanged;
use App\Services\Notification\ScheduleNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends the workspace notifications for a booking and every change after it
 * (ScheduleNotificationService). Queued, so sending them can never fail the
 * change.
 */
class SendScheduleNotifications implements ShouldQueue
{
    public function __construct(
        public readonly ScheduleNotificationService $notifications,
    ) {}

    public function handle(ScheduleBooked|ScheduleChanged $event): void
    {
        if ($event instanceof ScheduleBooked) {
            $this->notifications->booked($event->schedule);

            return;
        }

        $this->notifications->changed($event->schedule, $event->kind, CarbonImmutable::parse($event->arrivalAt), $event->changedBy, $event->reason);
    }
}

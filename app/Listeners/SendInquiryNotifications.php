<?php

namespace App\Listeners;

use App\Events\InquiryChangedByClient;
use App\Events\InquiryStatusChanged;
use App\Events\InquirySubmitted;
use App\Services\Notification\InquiryNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends the workspace notifications for a new inquiry, the client's own
 * changes to it, and every status move (InquiryNotificationService). Queued,
 * so sending them can never fail the change.
 */
class SendInquiryNotifications implements ShouldQueue
{
    public function __construct(
        public readonly InquiryNotificationService $notifications,
    ) {}

    public function handle(InquirySubmitted|InquiryChangedByClient|InquiryStatusChanged $event): void
    {
        match (true) {
            $event instanceof InquirySubmitted => $this->notifications->submitted($event->inquiry, $event->createdBy),
            $event instanceof InquiryChangedByClient => $this->notifications->changedByClient($event->inquiry, $event->change),
            default => $this->notifications->statusChanged($event),
        };
    }
}

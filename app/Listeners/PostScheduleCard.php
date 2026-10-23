<?php

namespace App\Listeners;

use App\Enums\ScheduleStatus;
use App\Events\Message\MessageCreated;
use App\Events\ScheduleBooked;
use App\Models\Schedule;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Posts a schedule card announcing a booking, into the thread
 * SupportConversationService::scheduleCardThreadFor() picks.
 *
 * Queued, so posting the card can never fail the booking. Retries are safe:
 * the card is unique per conversation, schedule and status, and a second
 * insert is ignored.
 *
 * Only a new booking posts a card for now. A status change (approval, cancel)
 * would call post() with the new status.
 */
class PostScheduleCard implements ShouldQueue
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly MessageRepositoryInterface $messages,
        public readonly MessageService $messageService,
        public readonly SupportConversationService $support,
    ) {}

    public function handle(ScheduleBooked $event): void
    {
        $this->post($event->schedule, $event->schedule->status);
    }

    public function post(Schedule $schedule, ScheduleStatus $announcedStatus): void
    {
        $conversation = $this->support->scheduleCardThreadFor($schedule);

        $message = $this->messages->createScheduleCardOnce($conversation->id, $schedule->id, $announcedStatus->value);

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'schedule');
    }
}

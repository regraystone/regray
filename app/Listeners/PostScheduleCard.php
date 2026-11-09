<?php

namespace App\Listeners;

use App\Enums\ScheduleCardStatus;
use App\Events\Message\MessageCreated;
use App\Events\ScheduleBooked;
use App\Events\ScheduleChanged;
use App\Models\Schedule;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Posts a schedule card into the client's Regray thread
 * (SupportConversationService::scheduleCardThreadFor()), for a booking and for
 * every change after it: updated, cancelled, approved, served, did not attend,
 * expired. Never into an inquiry's group chat, and never for an inquiry's
 * visit, which follows its inquiry and is announced by the inquiry's own card.
 *
 * Each card keeps the arrival and the reason as its change left them, so an
 * older card does not move when the appointment does.
 *
 * Queued, so posting the card can never fail the change. Retries are safe: the
 * card is unique per conversation, appointment, status and revision, and a
 * second insert is ignored.
 */
class PostScheduleCard implements ShouldQueue
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly MessageRepositoryInterface $messages,
        public readonly MessageService $messageService,
        public readonly SupportConversationService $support,
    ) {}

    public function handle(ScheduleBooked|ScheduleChanged $event): void
    {
        $schedule = $event->schedule;

        if ($event instanceof ScheduleBooked) {
            $this->post($schedule, ScheduleCardStatus::forStatus($schedule->status), $schedule->revision, $schedule->arrival_at, null);

            return;
        }

        $this->post($schedule, $event->announced, $event->revision, CarbonImmutable::parse($event->arrivalAt), $event->reason);
    }

    public function post(Schedule $schedule, ScheduleCardStatus $announced, int $revision, CarbonInterface $arrivalAt, ?string $reason): void
    {
        // A walk-in has no account and so no thread; an inquiry's visit has its inquiry's cards.
        if ($schedule->client_id === null || $schedule->isInquiryVisit()) {
            return;
        }

        $conversation = $this->support->scheduleCardThreadFor($schedule);

        $message = $this->messages->createScheduleCardOnce($conversation->id, $schedule->id, $announced->value, $revision, $arrivalAt, $reason);

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'schedule');
    }
}

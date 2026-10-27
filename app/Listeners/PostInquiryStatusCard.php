<?php

namespace App\Listeners;

use App\Events\InquiryStatusChanged;
use App\Events\Message\MessageCreated;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Posts an inquiry card announcing the inquiry's snapshot (its display status
 * and its four role statuses) every time one of its statuses changes,
 * including the first one: a newly sent inquiry posts a "New inquiry" card.
 * The snapshot is the inquiry as it is when the card is posted.
 *
 * Where the card goes is SupportConversationService::cardThreadFor():
 * - the inquiry's group chat, once the inquiry has one;
 * - otherwise the client's Regray Team thread, created if the client has none
 *   yet, so the team hears about every inquiry from the start.
 *
 * Queued, so posting the card can never fail the request that changed the
 * status. A newly sent inquiry would otherwise be reported as not saved.
 * Retries are safe: the card is unique per conversation, inquiry and
 * snapshot, and a second insert is ignored.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then
 * no real inquiry has a group, so every card goes to the Regray Team thread.
 * Schedule cards are PostScheduleCard's.
 */
class PostInquiryStatusCard implements ShouldQueue
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly MessageRepositoryInterface $messages,
        public readonly MessageService $messageService,
        public readonly SupportConversationService $support,
    ) {}

    public function handle(InquiryStatusChanged $event): void
    {
        $inquiry = $event->inquiry;
        $conversation = $this->support->cardThreadFor($inquiry);

        $message = $this->messages->createInquiryCardOnce(
            $conversation->id,
            $inquiry->id,
            $inquiry->status,
            $inquiry->marketing_status,
            $inquiry->sales_status,
            $inquiry->payment_status,
            $inquiry->process_status,
        );

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'inquiry');
    }
}

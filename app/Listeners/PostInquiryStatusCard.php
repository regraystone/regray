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
 * and its three role statuses) every time what it shows changes: a role status
 * moved, the inquiry was just sent ("New inquiry"), or its lead was lost.
 * The snapshot is the inquiry as it is when the card is posted.
 *
 * Where the card goes is SupportConversationService::cardThreadFor():
 * - the inquiry's group chat, once marketing has made one for it;
 * - otherwise the client's Regray Team thread, created if the client has none
 *   yet, so the team hears about every inquiry from the start;
 * - nowhere, for a direct (walk-in) client with no group chat.
 *
 * Queued, so posting the card can never fail the request that changed the
 * status. A newly sent inquiry would otherwise be reported as not saved.
 * Retries are safe: the card is unique per conversation, inquiry and
 * snapshot, and a second insert is ignored.
 *
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

        // A direct (walk-in) client's inquiry with no group chat has nowhere to post.
        if ($conversation === null) {
            return;
        }

        $message = $this->messages->createInquiryCardOnce(
            $conversation->id,
            $inquiry->id,
            $inquiry->status,
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

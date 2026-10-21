<?php

namespace App\Listeners;

use App\Events\InquiryStatusChanged;
use App\Events\Message\MessageCreated;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Posts an inquiry card announcing the new status every time an inquiry's
 * status changes, including the first one: a newly sent inquiry posts a
 * "New inquiry" card.
 *
 * Where the card goes:
 * - the inquiry's group chat, once the inquiry has one;
 * - otherwise the client's Regray Team thread, created if the client has none
 *   yet, so the team hears about every inquiry from the start.
 *
 * Queued, so posting the card can never fail the request that changed the
 * status. A newly sent inquiry would otherwise be reported as not saved.
 * Retries are safe: the card is unique per conversation, inquiry and status,
 * and a second insert is ignored.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then
 * no real inquiry has a group, so every card goes to the Regray Team thread.
 * TODO(booking): schedule cards follow the same rule (see Schedule).
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
        $conversation = $this->conversationFor($event->inquiry);

        $message = $this->messages->createInquiryCardOnce($conversation->id, $event->inquiry->id, $event->to->value);

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'inquiry');
    }

    /** The inquiry's group chat, or else its client's Regray Team thread. */
    private function conversationFor(Inquiry $inquiry): Conversation
    {
        return $this->conversations->groupForInquiry($inquiry->id)
            ?? $this->support->ensureFor($inquiry->loadMissing('client')->client);
    }
}

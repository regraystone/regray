<?php

namespace App\Listeners;

use App\Events\InquiryStatusChanged;
use App\Events\Message\MessageCreated;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;

/**
 * Posts an inquiry card into the inquiry's group chat when its status changes,
 * announcing the new status. Does nothing when the inquiry has no group chat.
 *
 * A retried listener cannot post the same change twice: the card is unique per
 * conversation, inquiry and status, and a second insert is ignored.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then no
 * group is linked to an inquiry, so this does nothing for real data.
 */
class PostInquiryStatusCard
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly MessageRepositoryInterface $messages,
        public readonly MessageService $messageService,
    ) {}

    public function handle(InquiryStatusChanged $event): void
    {
        $conversation = $this->conversations->groupForInquiry($event->inquiry->id);

        if ($conversation === null) {
            return;
        }

        $message = $this->messages->createInquiryCardOnce($conversation->id, $event->inquiry->id, $event->to->value);

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'inquiry');
    }
}

<?php

namespace App\Listeners;

use App\Data\Message\QuotationCardSnapshot;
use App\Events\Message\MessageCreated;
use App\Events\QuotationSent;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Posts a quotation card for every quotation Sales sends, a revision included:
 * a card of its own per document, never an edit of an earlier one. It is
 * posted in addition to the inquiry card that announces the move ("Quotation
 * sent", "Quotation revised"), into the same thread
 * (SupportConversationService::cardThreadFor()), and nowhere for a direct
 * (walk-in) client with no group chat.
 *
 * The card shows the document as it is now (QuotationCardSnapshot). Who may
 * open it is the file route's (InquiryFileController and
 * InquiryFileVisibility), so a reader who may not see the document gets that
 * route's 404.
 *
 * Queued, like PostInquiryStatusCard, so posting it can never fail the
 * request that sent the quotation. Retries are safe: the card is unique per
 * conversation and document, and a second insert is ignored.
 */
class PostQuotationCard implements ShouldQueue
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly MessageRepositoryInterface $messages,
        public readonly MessageService $messageService,
        public readonly SupportConversationService $support,
    ) {}

    public function handle(QuotationSent $event): void
    {
        $file = $event->file;
        $inquiry = $file->loadMissing('inquiry')->inquiry;
        $conversation = $this->support->cardThreadFor($inquiry);

        if ($conversation === null) {
            return;
        }

        $message = $this->messages->createQuotationCardOnce(
            $conversation->id,
            $inquiry->id,
            $file->id,
            QuotationCardSnapshot::of($file),
        );

        if ($message === null) {
            return;
        }

        $this->conversations->touch($conversation->id);

        broadcast(new MessageCreated($this->messages->loadForDisplay($message), $conversation));
        $this->messageService->announceActivity($conversation, 'inquiry');
    }
}

<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\ConversationService;
use App\Services\Notification\MessageNotificationService;
use Illuminate\Database\Eloquent\Collection;

class ShowThreadAction
{
    public function __construct(
        public readonly ConversationService $conversationService,
        public readonly MessageNotificationService $messageNotifications,
    ) {}

    /**
     * The thread's newest messages, or a page before or after the ones the
     * reader has. Opening it (any load but an older page) cancels the reader's
     * message notification for it.
     *
     * @return array{conversation: Conversation, messages: Collection<int, Message>, hasMore: bool, pointers: list<array{userId: int, name: string, lastReadMessageId: int|null, lastDeliveredMessageId: int|null}>, assignment: array<string, mixed>|null, canRequestQuote: bool}
     */
    public function execute(User $user, Conversation $conversation, ?int $before, ?int $after, ?int $limit): array
    {
        if ($before === null) {
            $this->messageNotifications->closeFor($user, $conversation);
        }

        return [
            'conversation' => $this->conversationService->summary($user, $conversation),
            ...$this->conversationService->thread($user, $conversation, $before, $after, $limit),
        ];
    }
}

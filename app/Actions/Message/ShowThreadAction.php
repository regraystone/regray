<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\ConversationService;
use Illuminate\Database\Eloquent\Collection;

class ShowThreadAction
{
    public function __construct(
        public readonly ConversationService $conversationService,
    ) {}

    /**
     * @return array{conversation: Conversation, messages: Collection<int, Message>, hasMore: bool, pointers: list<array{userId: int, name: string, lastReadMessageId: int|null, lastDeliveredMessageId: int|null}>, assignment: array<string, mixed>|null}
     */
    public function execute(User $user, Conversation $conversation, ?int $before, ?int $after, ?int $limit): array
    {
        return [
            'conversation' => $this->conversationService->summary($user, $conversation),
            ...$this->conversationService->thread($user, $conversation, $before, $after, $limit),
        ];
    }
}

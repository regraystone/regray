<?php

namespace App\Actions\Message;

use App\Data\Message\ConversationListFilters;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Message\ConversationService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListConversationsAction
{
    public function __construct(
        public readonly ConversationService $conversationService,
    ) {}

    /**
     * @return array{conversations: LengthAwarePaginator<int, Conversation>, active: Conversation|null}
     */
    public function execute(User $user, ConversationListFilters $filters, ?string $requested): array
    {
        $conversations = $this->conversationService->list($user, $filters);
        $active = $this->conversationService->requested($user, $requested);

        return [
            'conversations' => $conversations,
            'active' => $active !== null && $user->can('view', $active)
                ? $this->conversationService->summary($user, $active)
                : null,
        ];
    }
}

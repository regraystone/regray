<?php

namespace App\Actions\Message;

use App\Data\Message\ConversationListFilters;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Message\ConversationService;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class ListConversationsAction
{
    public function __construct(
        public readonly ConversationService $conversationService,
    ) {}

    /**
     * The list comes back unread, as a closure, so the page can defer it: the
     * first paint shows the list's skeleton while the open thread, which is
     * resolved here, is ready at once.
     *
     * @return array{conversations: Closure(): LengthAwarePaginator<int, Conversation>, active: Conversation|null}
     */
    public function execute(User $user, ConversationListFilters $filters, ?string $requested): array
    {
        $active = $this->conversationService->requested($user, $requested);

        return [
            'conversations' => fn (): LengthAwarePaginator => $this->conversationService->list($user, $filters),
            'active' => $active !== null && $user->can('view', $active)
                ? $this->conversationService->summary($user, $active)
                : null,
        ];
    }
}

<?php

namespace App\Actions\Message;

use App\Models\User;
use App\Services\Message\GroupConversationService;
use Illuminate\Database\Eloquent\Collection;

class SearchPeopleAction
{
    public function __construct(
        public readonly GroupConversationService $groupService,
    ) {}

    /**
     * @return array{sales: Collection<int, User>, technical: Collection<int, User>, clients: Collection<int, User>, inquiries: list<array{id: int, reference: string, name: string, hasGroup: bool, isWon: bool}>}
     */
    public function execute(User $creator, ?string $search, ?int $clientId = null): array
    {
        return $this->groupService->people($creator, $search, $clientId);
    }
}

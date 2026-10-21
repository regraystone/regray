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
     * @return array{staff: Collection<int, User>, clients: Collection<int, User>}
     */
    public function execute(?string $search): array
    {
        return $this->groupService->people($search);
    }
}

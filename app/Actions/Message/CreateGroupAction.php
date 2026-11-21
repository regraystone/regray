<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Message\GroupConversationService;
use Illuminate\Support\Facades\DB;

class CreateGroupAction
{
    public function __construct(
        public readonly GroupConversationService $groupService,
    ) {}

    /**
     * @param  list<int>  $staffIds
     */
    public function execute(User $creator, string $name, array $staffIds, int $clientId, int $inquiryId): Conversation
    {
        return DB::transaction(fn (): Conversation => $this->groupService->create($creator, $name, $staffIds, $clientId, $inquiryId));
    }

    /**
     * A walk-in inquiry's group chat, in one transaction (GroupConversationService::createForWalkIn()).
     *
     * @param  list<int>  $staffIds
     */
    public function executeForWalkIn(User $creator, string $name, array $staffIds, int $inquiryId): Conversation
    {
        return DB::transaction(fn (): Conversation => $this->groupService->createForWalkIn($creator, $name, $staffIds, $inquiryId));
    }
}

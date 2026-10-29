<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\AddGroupMembersRequest;
use App\Http\Requests\Message\SearchPeopleRequest;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\PersonResource;
use App\Models\Conversation;
use App\Services\Message\ConversationService;
use App\Services\Message\GroupConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Adding accounting and logistics to an inquiry's group chat, by a sales member
 * of it. The route's `can:addMembers` (ConversationPolicy) settles who may.
 */
class GroupMemberController extends Controller
{
    public function __construct(
        public readonly GroupConversationService $groupService,
        public readonly ConversationService $conversationService,
    ) {}

    /** Accounting and logistics users not in the group yet, matching a search. */
    public function index(SearchPeopleRequest $request, Conversation $conversation): JsonResponse
    {
        return response()->json([
            'people' => PersonResource::collection($this->groupService->addableTo($conversation, $request->search()))->resolve($request),
        ]);
    }

    public function store(AddGroupMembersRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        DB::transaction(fn () => $this->groupService->addMembers($conversation, $request->userIds()));

        return response()->json([
            'conversation' => ConversationResource::make($this->conversationService->summary($user, $conversation))->viewedBy($user)->resolve($request),
        ]);
    }
}

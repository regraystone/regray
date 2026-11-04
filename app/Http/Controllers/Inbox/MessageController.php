<?php

namespace App\Http\Controllers\Inbox;

use App\Actions\Message\CreateGroupAction;
use App\Actions\Message\ListConversationsAction;
use App\Actions\Message\SearchPeopleAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\CreateGroupRequest;
use App\Http\Requests\Message\ListConversationsRequest;
use App\Http\Requests\Message\SearchPeopleRequest;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\PersonResource;
use App\Services\Message\ConversationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inbox page and the two things it does without a thread open: starting
 * a group for one client's inquiry, and finding the people and the inquiry to
 * start it with.
 */
class MessageController extends Controller
{
    public function __construct(
        public readonly ListConversationsAction $listConversations,
        public readonly CreateGroupAction $createGroup,
        public readonly SearchPeopleAction $searchPeople,
        public readonly ConversationService $conversationService,
    ) {}

    public function index(ListConversationsRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $result = $this->listConversations->execute($user, $filters, $request->requestedConversation());

        return Inertia::render('inbox', [
            'conversations' => Inertia::scroll(fn () => ConversationResource::collection($result['conversations']()))->defer(),
            'filters' => [
                'search' => $filters->search ?? '',
                'filter' => $filters->filter,
            ],
            'activeConversation' => $result['active'] === null
                ? null
                : (ConversationResource::make($result['active'])->viewedBy($user))->resolve($request),
            'currentUserId' => $user->id,
            'isClient' => $user->isClient(),
            'canCreateGroup' => $user->can(Permission::ConversationsCreate->value),
        ]);
    }

    public function people(SearchPeopleRequest $request): JsonResponse
    {
        $people = $this->searchPeople->execute($request->user(), $request->search(), $request->clientId());

        return response()->json([
            'staff' => PersonResource::collection($people['staff'])->resolve($request),
            'clients' => PersonResource::collection($people['clients'])->resolve($request),
            'owners' => PersonResource::collection($people['owners'])->resolve($request),
            'inquiries' => $people['inquiries'],
        ]);
    }

    public function storeGroup(CreateGroupRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->createGroup->execute($user, $request->name(), $request->staffIds(), $request->clientId(), $request->inquiryId());

        return response()->json([
            'conversation' => ConversationResource::make($this->conversationService->summary($user, $conversation))->viewedBy($user)->resolve($request),
        ], 201);
    }
}

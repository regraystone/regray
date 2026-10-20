<?php

namespace App\Http\Controllers\Message;

use App\Actions\Message\AssignSupportConversationAction;
use App\Actions\Message\ChangeMessageAction;
use App\Actions\Message\MovePointerAction;
use App\Actions\Message\SendMessageAction;
use App\Actions\Message\ShowThreadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\MovePointerRequest;
use App\Http\Requests\Message\ReassignConversationRequest;
use App\Http\Requests\Message\SendMessageRequest;
use App\Http\Requests\Message\ThreadMessagesRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One thread, as JSON: its messages, writing in it, the receipts, and handing a
 * Regray Team thread from one staff member to another.
 *
 * Access is settled by the route's `can:` middleware against ConversationPolicy
 * before any of these run.
 */
class ThreadController extends Controller
{
    public function __construct(
        public readonly ShowThreadAction $showThread,
        public readonly SendMessageAction $sendMessage,
        public readonly ChangeMessageAction $changeMessage,
        public readonly MovePointerAction $movePointer,
        public readonly AssignSupportConversationAction $assignSupport,
        public readonly ConversationService $conversationService,
    ) {}

    public function messages(ThreadMessagesRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $thread = $this->showThread->execute($user, $conversation, $request->before(), $request->after(), $request->limit());

        return response()->json([
            'conversation' => (ConversationResource::make($thread['conversation'])->viewedBy($user))->resolve($request),
            'messages' => $this->resolveMessages($thread['messages'], $conversation, $user, $request),
            'hasMore' => $thread['hasMore'],
            'pointers' => $thread['pointers'],
            'assignment' => $thread['assignment'],
        ]);
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $result = $this->sendMessage->execute($user, $conversation, $request->body(), $request->replyToId(), $request->clientKey());

        return response()->json([
            'message' => (new MessageResource($result['message'], $conversation, $user))->resolve($request),
        ], $result['created'] ? 201 : 200);
    }

    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $message = $this->changeMessage->edit($user, $message, $request->body());

        return response()->json([
            'message' => (new MessageResource($message, $message->conversation, $user))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $message = $this->changeMessage->remove($user, $message);

        return response()->json([
            'message' => (new MessageResource($message, $message->conversation, $user))->resolve($request),
        ]);
    }

    public function pointer(MovePointerRequest $request, Conversation $conversation): JsonResponse
    {
        $this->movePointer->execute($request->user(), $conversation, $request->messageId(), $request->isRead());

        return response()->json(['pointers' => $this->conversationService->pointers($conversation)]);
    }

    public function claim(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->assignment($request, $this->assignSupport->claim($request->user(), $conversation));
    }

    public function release(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->assignment($request, $this->assignSupport->release($request->user(), $conversation));
    }

    public function reassign(ReassignConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $target = User::query()->findOrFail($request->userId());

        return $this->assignment($request, $this->assignSupport->reassign($request->user(), $conversation, $target));
    }

    private function assignment(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'conversation' => ConversationResource::make($this->conversationService->summary($user, $conversation))->viewedBy($user)->resolve($request),
            'assignment' => $this->conversationService->assignmentFor($user, $conversation),
        ]);
    }

    /**
     * @param  iterable<Message>  $messages
     * @return list<array<string, mixed>>
     */
    private function resolveMessages(iterable $messages, Conversation $conversation, User $user, Request $request): array
    {
        $out = [];

        foreach ($messages as $message) {
            $out[] = (new MessageResource($message, $conversation, $user))->resolve($request);
        }

        return $out;
    }
}

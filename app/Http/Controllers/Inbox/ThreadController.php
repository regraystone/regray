<?php

namespace App\Http\Controllers\Inbox;

use App\Actions\Message\AssignSupportConversationAction;
use App\Actions\Message\ChangeMessageAction;
use App\Actions\Message\MovePointerAction;
use App\Actions\Message\SendMessageAction;
use App\Actions\Message\ShowThreadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\MovePointerRequest;
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
 * One thread, as JSON: its messages, writing in it, the receipts, and claiming
 * a Regray Team thread.
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
        $thread = $this->showThread->execute($user, $conversation, $request->beforeId(), $request->afterId(), $request->limitValue());

        return response()->json([
            'conversation' => ConversationResource::make($thread['conversation'])->viewedBy($user)->withSendAbility()->resolve($request),
            'messages' => $this->resolveMessages($thread['messages'], $conversation, $user, $request),
            'hasMore' => $thread['hasMore'],
            'pointers' => $thread['pointers'],
            'assignment' => $thread['assignment'],
            'canRequestQuote' => $thread['canRequestQuote'],
        ]);
    }

    /**
     * One message, as the conversation's channel announced it: the channel
     * carries ids only, so its content is always fetched here, under the
     * conversation's own policy, and stops reaching anyone who has lost
     * access, whatever connection they still hold.
     */
    public function message(Request $request, Conversation $conversation, int $message): JsonResponse
    {
        $found = $this->conversationService->message($conversation, $message) ?? abort(404);

        return response()->json([
            'message' => (new MessageResource($found, $conversation, $request->user()))->resolve($request),
        ]);
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $result = $this->sendMessage->execute($user, $conversation, $request->body(), $request->replyToId(), $request->clientKey(), $request->attachments());

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

    private function assignment(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'conversation' => ConversationResource::make($this->conversationService->summary($user, $conversation))->viewedBy($user)->withSendAbility()->resolve($request),
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

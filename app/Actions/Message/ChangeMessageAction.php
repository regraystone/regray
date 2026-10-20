<?php

namespace App\Actions\Message;

use App\Models\Message;
use App\Models\User;
use App\Services\Message\MessageService;
use Illuminate\Support\Facades\Gate;

/** Edit or remove one's own text message. */
class ChangeMessageAction
{
    public function __construct(
        public readonly MessageService $messageService,
    ) {}

    public function edit(User $user, Message $message, string $body): Message
    {
        $conversation = $message->conversation;
        Gate::forUser($user)->authorize('view', $conversation);

        return $this->messageService->edit($user, $message, $body, $conversation);
    }

    public function remove(User $user, Message $message): Message
    {
        $conversation = $message->conversation;
        Gate::forUser($user)->authorize('view', $conversation);

        return $this->messageService->remove($user, $message, $conversation);
    }
}

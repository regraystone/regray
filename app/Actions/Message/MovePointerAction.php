<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Message\MessageService;

class MovePointerAction
{
    public function __construct(
        public readonly MessageService $messageService,
    ) {}

    public function execute(User $user, Conversation $conversation, int $messageId, bool $read): void
    {
        $read
            ? $this->messageService->markRead($user, $conversation, $messageId)
            : $this->messageService->markDelivered($user, $conversation, $messageId);
    }
}

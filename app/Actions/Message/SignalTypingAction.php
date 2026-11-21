<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Message\MessageService;

class SignalTypingAction
{
    public function __construct(
        public readonly MessageService $messageService,
    ) {}

    public function execute(User $user, Conversation $conversation): void
    {
        $this->messageService->signalTyping($user, $conversation);
    }
}

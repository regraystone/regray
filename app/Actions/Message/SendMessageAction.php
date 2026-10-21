<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\MessageService;

class SendMessageAction
{
    public function __construct(
        public readonly MessageService $messageService,
    ) {}

    /**
     * @return array{message: Message, created: bool}
     */
    public function execute(User $sender, Conversation $conversation, string $body, ?int $replyToId, string $clientKey): array
    {
        return $this->messageService->send($sender, $conversation, $body, $replyToId, $clientKey);
    }
}

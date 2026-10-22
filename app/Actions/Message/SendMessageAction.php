<?php

namespace App\Actions\Message;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\MessageService;
use Illuminate\Http\UploadedFile;

class SendMessageAction
{
    public function __construct(
        public readonly MessageService $messageService,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return array{message: Message, created: bool}
     */
    public function execute(User $sender, Conversation $conversation, string $body, ?int $replyToId, string $clientKey, array $files = []): array
    {
        return $this->messageService->send($sender, $conversation, $body, $replyToId, $clientKey, $files);
    }
}

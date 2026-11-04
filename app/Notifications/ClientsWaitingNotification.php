<?php

namespace App\Notifications;

use App\Services\Message\MessageReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Clients are waiting": sent to the support pool about Regray Team threads
 * nobody has claimed, where the client has written and is still waiting.
 *
 * Names the clients and counts their messages, never quotes them, for the
 * same reason UnreadMessagesNotification does not.
 *
 * @phpstan-import-type DueThread from MessageReminderService
 */
class ClientsWaitingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<DueThread>  $threads
     */
    public function __construct(
        public readonly array $threads,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clients = count($this->threads);

        $mail = (new MailMessage)
            ->subject($clients === 1 ? 'A client is waiting for a reply' : "{$clients} clients are waiting for a reply")
            ->line('Nobody has claimed these Regray conversations yet:');

        foreach ($this->threads as $thread) {
            $mail->line("{$thread['name']}: {$thread['count']} ".($thread['count'] === 1 ? 'message' : 'messages'));
        }

        return $mail
            ->line('Claim a conversation to answer it.')
            ->action('Open messages', $clients === 1
                ? route('inbox.index', ['conversation' => $this->threads[0]['conversationId']])
                : route('inbox.index'));
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that goes with a message notification, once its timer is due
 * (MessageNotificationService::deliver()): the same line as the bell's, and
 * a link to the thread.
 *
 * Says who, never what: a message's text stays on the site, behind the login,
 * rather than in an inbox the app cannot protect.
 */
class MessageWaitingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $headline,
        public readonly int $conversationId,
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
        return (new MailMessage)
            ->subject($this->headline)
            ->line($this->headline)
            ->line('Open Regray to read it.')
            ->action('Open the conversation', route('inbox.index', ['conversation' => $this->conversationId]));
    }
}

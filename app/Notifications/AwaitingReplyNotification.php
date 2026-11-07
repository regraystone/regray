<?php

namespace App\Notifications;

use App\Services\Message\MessageReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Waiting for your reply": to a client who has not answered the team's messages,
 * whether or not they have read them.
 *
 * Says who and how many, never what, for the same reason
 * UnreadMessagesNotification does not.
 *
 * @phpstan-import-type DueThread from MessageReminderService
 */
class AwaitingReplyNotification extends Notification implements ShouldQueue
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
        $total = array_sum(array_column($this->threads, 'count'));

        $mail = (new MailMessage)
            ->subject($total === 1 ? 'A message is waiting for your reply' : "{$total} messages are waiting for your reply")
            ->line('These are waiting for your reply on Regray:');

        foreach ($this->threads as $thread) {
            $mail->line("{$thread['name']}: {$thread['count']} ".($thread['count'] === 1 ? 'message' : 'messages'));
        }

        return $mail->action('Open messages', count($this->threads) === 1
            ? route('inbox.index', ['conversation' => $this->threads[0]['conversationId']])
            : route('inbox.index'));
    }
}

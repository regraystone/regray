<?php

namespace App\Notifications;

use App\Services\Message\MessageReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You have unread messages": one email for every conversation where the
 * reader has messages waiting.
 *
 * Says who and how many, never what: a message's text stays on the site,
 * behind the login, rather than in an inbox the app cannot protect.
 *
 * @phpstan-import-type DueThread from MessageReminderService
 */
class UnreadMessagesNotification extends Notification implements ShouldQueue
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
            ->subject($total === 1 ? 'You have a new message' : "You have {$total} new messages")
            ->line('These are waiting for you on Regray:');

        foreach ($this->threads as $thread) {
            $mail->line("{$thread['name']}: {$thread['count']} new ".($thread['count'] === 1 ? 'message' : 'messages'));
        }

        return $mail->action('Open messages', $this->link());
    }

    /** The one conversation when there is only one, the whole list otherwise. */
    private function link(): string
    {
        return count($this->threads) === 1
            ? route('inbox.index', ['conversation' => $this->threads[0]['conversationId']])
            : route('inbox.index');
    }
}

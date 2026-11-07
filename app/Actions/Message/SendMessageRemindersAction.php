<?php

namespace App\Actions\Message;

use App\Notifications\AwaitingReplyNotification;
use App\Notifications\ClientsWaitingNotification;
use App\Notifications\UnreadMessagesNotification;
use App\Services\Message\MessageReminderService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

/**
 * Email everyone with messages waiting past `messaging.reminders.after_minutes`:
 * each staff member about their own unread messages, and the support pool about
 * clients nobody has claimed yet. Clients are emailed about messages they have not
 * replied to, after the longer `client_after_minutes`.
 *
 * The email is queued first and the marker moved after, so a failure between
 * the two can repeat an email but never lose one.
 */
class SendMessageRemindersAction
{
    public function __construct(
        public readonly MessageReminderService $reminders,
    ) {}

    /**
     * @return array{members: int, clients: int, pool: int}
     */
    public function execute(?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        $cutoff = $now->copy()->subMinutes((int) config('messaging.reminders.after_minutes'));
        $clientCutoff = $now->copy()->subMinutes((int) config('messaging.reminders.client_after_minutes'));

        return [
            'members' => $this->remindMembers($cutoff),
            'clients' => $this->remindClients($clientCutoff),
            'pool' => $this->remindPool($cutoff),
        ];
    }

    private function remindMembers(CarbonInterface $cutoff): int
    {
        $due = $this->reminders->dueForMembers($cutoff);

        foreach ($due as $reminder) {
            $reminder['user']->notify(new UnreadMessagesNotification($reminder['threads']));

            foreach ($reminder['threads'] as $thread) {
                $this->reminders->markMemberNotified($thread['conversationId'], $reminder['user']->id, $thread['latestId']);
            }
        }

        return count($due);
    }

    private function remindClients(CarbonInterface $cutoff): int
    {
        $due = $this->reminders->dueForClients($cutoff);

        foreach ($due as $reminder) {
            $reminder['user']->notify(new AwaitingReplyNotification($reminder['threads']));

            foreach ($reminder['threads'] as $thread) {
                $this->reminders->markMemberNotified($thread['conversationId'], $reminder['user']->id, $thread['latestId']);
            }
        }

        return count($due);
    }

    private function remindPool(CarbonInterface $cutoff): int
    {
        $threads = $this->reminders->dueForPool($cutoff);

        if ($threads === []) {
            return 0;
        }

        $pool = $this->reminders->poolMembers();

        if ($pool->isEmpty()) {
            return 0;
        }

        Notification::send($pool, new ClientsWaitingNotification($threads));

        foreach ($threads as $thread) {
            $this->reminders->markPoolNotified($thread['conversationId'], $thread['latestId']);
        }

        return $pool->count();
    }
}

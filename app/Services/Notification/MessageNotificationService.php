<?php

namespace App\Services\Notification;

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Jobs\DeliverMessageNotification;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\InquiryHandler;
use App\Models\Message;
use App\Models\PendingNotification;
use App\Models\User;
use App\Notifications\MessageWaitingNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Message notifications and their emails, which go together after a delay:
 * `messaging.notifications.staff_delay_minutes` for staff and
 * `client_delay_minutes` for clients, from the first message the recipient has
 * not seen.
 *
 * Who is told about a person's text message (recipientsFor()):
 * - a Regray Team thread: the client's message goes to every marketing user
 *   while nobody has claimed the thread, and to its assignee after; a staff
 *   message goes to the client;
 * - an inquiry's group chat: the client's message goes to the staff assigned
 *   on the inquiry (inquiry_handlers), never to marketing; a staff message
 *   goes to the client alone;
 * - any other conversation (a direct chat, a group bound to no inquiry): to
 *   its other members.
 * Never the sender, never a member who muted the thread, and never the owner
 * or the admin, who get no message notifications.
 *
 * One open timer per recipient and thread (`pending_notifications`): later
 * messages add nothing until the recipient opens the thread (closeFor()),
 * which cancels a timer still running. The next message after that starts a
 * new one. Delivery (deliver()) runs on the queue at the due time, and again
 * from `notifications:deliver-due` every minute for any the queue missed. It
 * is idempotent: under the row's lock, a timer already sent or cancelled is
 * left alone, and one whose recipient no longer qualifies is cancelled.
 */
class MessageNotificationService
{
    public function __construct(
        public readonly NotificationService $notifications,
    ) {}

    /**
     * Start the timers for a person's text message just sent: one for each
     * recipient who has none open in this thread.
     */
    public function messageSent(Message $message, Conversation $conversation, User $sender): void
    {
        $this->closeFor($sender, $conversation);

        $recipients = $this->recipientsFor($conversation, $sender);

        if ($recipients->isEmpty()) {
            return;
        }

        $now = Date::now();
        $rows = [];
        $bindings = [];

        foreach ($recipients as $recipient) {
            $rows[] = '(?, ?, ?, ?, ?)';
            array_push($bindings, $recipient->id, $conversation->id, $message->id, $now->copy()->addMinutes($this->delayMinutesFor($recipient)), $now);
        }

        $created = DB::select(
            'INSERT INTO pending_notifications (user_id, conversation_id, message_id, due_at, created_at) VALUES '
            .implode(', ', $rows).' ON CONFLICT DO NOTHING RETURNING id, due_at',
            $bindings,
        );

        foreach ($created as $row) {
            DeliverMessageNotification::dispatch((int) $row->id)->delay(Date::parse($row->due_at));
        }
    }

    /**
     * The user opened the thread: every timer of theirs in it closes, and one
     * still running is cancelled.
     */
    public function closeFor(User $user, Conversation $conversation): void
    {
        PendingNotification::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->whereNull('closed_at')
            ->update(['closed_at' => Date::now()]);
    }

    /**
     * Send one timer's notification and email, if it is due and still
     * qualifies. False when nothing was sent.
     */
    public function deliver(int $pendingId): bool
    {
        return DB::transaction(function () use ($pendingId): bool {
            $pending = PendingNotification::query()->whereKey($pendingId)->lockForUpdate()->first();

            if ($pending === null || $pending->sent_at !== null || $pending->closed_at !== null || $pending->due_at->isFuture()) {
                return false;
            }

            $user = User::query()->find($pending->user_id);
            $conversation = Conversation::query()->find($pending->conversation_id);
            $message = $pending->message_id === null ? null : Message::query()->withTrashed()->find($pending->message_id);
            $sender = $message?->sender_id === null ? null : User::query()->find($message->sender_id);

            if ($user === null || $conversation === null || $sender === null || ! $this->stillQualifies($user, $conversation, $sender)) {
                $pending->forceFill(['closed_at' => Date::now()])->save();

                return false;
            }

            $pending->forceFill(['sent_at' => Date::now()])->save();

            $body = $this->copyFor($user, $conversation, $sender);
            $this->notifications->send([$user], NotificationType::Message, $conversation->id, $body);

            if ($user->email_verified_at !== null) {
                $user->notify(new MessageWaitingNotification($body, $conversation->id));
            }

            return true;
        });
    }

    /**
     * Deliver every timer that is due and has not gone, for the ones the
     * queue missed. Returns how many were sent.
     */
    public function deliverDue(): int
    {
        $sent = 0;

        $ids = PendingNotification::query()
            ->whereNull('sent_at')
            ->whereNull('closed_at')
            ->where('due_at', '<=', Date::now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $sent += $this->deliver((int) $id) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * Who is told about `$sender`'s message in the conversation as it stands
     * now. See the class description.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(Conversation $conversation, User $sender): Collection
    {
        $query = User::query()->with('roles');

        if ($conversation->isSupport()) {
            if ($sender->id !== $conversation->support_client_id) {
                $query->whereKey($conversation->support_client_id);
            } elseif ($conversation->assigned_to !== null) {
                $query->whereKey($conversation->assigned_to);
            } else {
                $query->role(Role::Marketing->value);
            }
        } elseif ($conversation->isInquiryGroup()) {
            $query = $sender->isClient()
                ? $query->whereIn('id', InquiryHandler::query()->where('inquiry_id', $conversation->inquiry_id)->select('user_id'))
                : $query->whereIn('id', $this->activeMembers($conversation))->role(Role::Client->value);
        } else {
            $query->whereIn('id', $this->activeMembers($conversation));
        }

        $muted = ConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('muted_at')
            ->select('user_id');

        return $query
            ->whereKeyNot($sender->id)
            ->whereNotIn('id', $muted)
            ->get()
            ->reject(fn (User $user): bool => $user->hasRole([Role::Admin->value, Role::Owner->value]))
            ->values();
    }

    /**
     * The sentence the recipient reads, in the bell and as the email's
     * subject: "Carla sent a new message.", "Carla sent a new message in
     * Kitchen project.", "Mara sent you a new message."
     */
    public function copyFor(User $recipient, Conversation $conversation, User $sender): string
    {
        if ($recipient->isClient()) {
            return "{$sender->name} sent you a new message.";
        }

        if ($conversation->isSupport()) {
            return "{$sender->name} sent a new message.";
        }

        if ($conversation->name !== null && $conversation->name !== '' && $conversation->type === ConversationType::Group) {
            return "{$sender->name} sent a new message in {$conversation->name}.";
        }

        return "{$sender->name} sent you a new message.";
    }

    private function stillQualifies(User $user, Conversation $conversation, User $sender): bool
    {
        return $user->can('view', $conversation)
            && $this->recipientsFor($conversation, $sender)->contains(fn (User $recipient): bool => $recipient->id === $user->id);
    }

    private function delayMinutesFor(User $recipient): int
    {
        return $recipient->isClient()
            ? (int) config('messaging.notifications.client_delay_minutes')
            : (int) config('messaging.notifications.staff_delay_minutes');
    }

    /** @return Builder<ConversationMember> */
    private function activeMembers(Conversation $conversation): Builder
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', '!=', ConversationMemberRole::Viewer->value)
            ->select('user_id');
    }
}

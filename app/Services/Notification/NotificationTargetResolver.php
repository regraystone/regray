<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Repositories\Contracts\ConversationRepositoryInterface;

/**
 * Where a notification leads its reader, asked when they open it, so it
 * follows what they may open now rather than when it was sent. Null when they
 * can no longer open it: the bell then says the item is no longer available.
 *
 * - A message: the thread, in the Inbox.
 * - An inquiry: its details page, on the client's Quote list or the staff
 *   member's Inquiry list. A staff member who does not read it (marketing,
 *   before anyone has claimed the client) is taken to the client's Regray
 *   Team thread instead, where its card is, when they may read that.
 * - An appointment: the client's /profile/schedule, or the staff /schedule.
 */
class NotificationTargetResolver
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
    ) {}

    public function urlFor(User $user, UserNotification $notification): ?string
    {
        return match ($notification->type) {
            NotificationType::Message => $this->conversationUrl($user, Conversation::query()->find($notification->target_id)),
            NotificationType::Inquiry => $this->inquiryUrl($user, Inquiry::query()->find($notification->target_id)),
            NotificationType::Schedule => $this->scheduleUrl($user, Schedule::query()->find($notification->target_id)),
        };
    }

    private function conversationUrl(User $user, ?Conversation $conversation): ?string
    {
        if ($conversation === null || ! $user->can('view', $conversation)) {
            return null;
        }

        return route('inbox.index', ['conversation' => $conversation->id]);
    }

    private function inquiryUrl(User $user, ?Inquiry $inquiry): ?string
    {
        if ($inquiry === null) {
            return null;
        }

        if ($user->isClient()) {
            return $inquiry->client_id === $user->id ? route('quote.show', $inquiry->reference) : null;
        }

        if ($inquiry->isVisibleTo($user)) {
            return route('inquiry.show', $inquiry->reference);
        }

        $client = $inquiry->client_id === null ? null : User::query()->find($inquiry->client_id);

        return $client === null ? null : $this->conversationUrl($user, $this->conversations->findSupportFor($client));
    }

    private function scheduleUrl(User $user, ?Schedule $schedule): ?string
    {
        if ($schedule === null) {
            return null;
        }

        if ($user->isClient()) {
            return $schedule->client_id === $user->id ? route('profile.schedule') : null;
        }

        return $schedule->isVisibleTo($user) ? route('schedule.index') : null;
    }
}

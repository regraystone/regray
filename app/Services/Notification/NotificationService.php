<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Events\Notification\NotificationCreated;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The workspace bell: writing notifications, listing them, and reading them.
 *
 * Every notification is a row in `notifications`, pushed to its recipient
 * over their `user.{id}` channel (NotificationCreated). Who is sent what is
 * decided by the senders: MessageNotificationService,
 * ScheduleNotificationService and InquiryNotificationService.
 */
class NotificationService
{
    public const PER_PAGE = 10;

    public function __construct(
        public readonly NotificationTargetResolver $targets,
    ) {}

    /**
     * Send one line to each of `$recipients`, once each however often they
     * are listed.
     *
     * @param  iterable<User>  $recipients
     */
    public function send(iterable $recipients, NotificationType $type, int $targetId, string $body): void
    {
        $sent = [];

        foreach ($recipients as $recipient) {
            if (isset($sent[$recipient->id])) {
                continue;
            }

            $sent[$recipient->id] = true;

            $notification = UserNotification::query()->create([
                'user_id' => $recipient->id,
                'type' => $type,
                'body' => $body,
                'target_id' => $targetId,
                'created_at' => Date::now(),
            ]);

            event(new NotificationCreated($notification, $this->unreadCount($recipient)));
        }
    }

    /**
     * A page of the user's notifications, newest first, older than
     * `$beforeId` when it is given.
     *
     * @return array{notifications: Collection<int, UserNotification>, hasMore: bool}
     */
    public function page(User $user, ?int $beforeId): array
    {
        $rows = UserNotification::query()
            ->where('user_id', $user->id)
            ->when($beforeId !== null, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::PER_PAGE + 1)
            ->get();

        return [
            'notifications' => $rows->take(self::PER_PAGE)->values(),
            'hasMore' => $rows->count() > self::PER_PAGE,
        ];
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * Read one notification and say where it leads: null when the reader can
     * no longer open what it is about.
     */
    public function open(User $user, UserNotification $notification): ?string
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => Date::now()])->save();
        }

        return $this->targets->urlFor($user, $notification);
    }

    public function markAllRead(User $user): void
    {
        UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Date::now()]);
    }
}

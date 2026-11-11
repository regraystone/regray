<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One line in a user's workspace bell. Written by NotificationService, never
 * changed afterwards except to be read.
 *
 * Named apart from Laravel's DatabaseNotification, which this app does not
 * use: its own `notifications` table has its own columns.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property string $body
 * @property int $target_id
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'type', 'body', 'target_id', 'read_at', 'created_at'])]
class UserNotification extends Model
{
    use MassPrunable;

    /** How long a notification is kept, read or not. */
    public const KEEP_DAYS = 30;

    protected $table = 'notifications';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'target_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', Date::now()->subDays(self::KEEP_DAYS));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A message notification on its timer: one recipient, one thread
 * (MessageNotificationService). Open until the recipient opens the thread;
 * closed before it was sent, it is cancelled and never sent.
 *
 * @property int $id
 * @property int $user_id
 * @property int $conversation_id
 * @property int|null $message_id
 * @property Carbon $due_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'conversation_id', 'message_id', 'due_at', 'sent_at', 'closed_at', 'created_at'])]
class PendingNotification extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Rows older than the bell keeps its notifications, sent, cancelled or stuck.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', Date::now()->subDays(UserNotification::KEEP_DAYS));
    }
}

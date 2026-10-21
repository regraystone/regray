<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Enums\SystemEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $sender_id
 * @property MessageType $type
 * @property string|null $body
 * @property int|null $inquiry_id
 * @property int|null $schedule_id
 * @property string|null $announced_status
 * @property Carbon $created_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property int|null $reply_to_id
 * @property string|null $client_key
 * @property SystemEvent|null $system_event
 * @property int|null $system_user_id
 *
 * Retention: a removed message is soft-deleted and stays as a row for good.
 * The thread shows it as "Message removed" in its place, so replies to it
 * and the receipts pointing at it still make sense, and the record is kept.
 * Nothing prunes these rows; do not make this model Prunable without
 * deciding that policy again.
 */
#[Fillable(['conversation_id', 'sender_id', 'type', 'body', 'inquiry_id', 'schedule_id', 'announced_status', 'edited_at', 'reply_to_id', 'client_key', 'system_event', 'system_user_id', 'created_at'])]
class Message extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'system_event' => SystemEvent::class,
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'system_user_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}

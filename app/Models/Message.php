<?php

namespace App\Models;

use App\Enums\MessageType;
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
 * @property int|null $agenda_id
 * @property string|null $announced_status
 * @property Carbon $created_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['sender_id', 'type', 'body', 'inquiry_id', 'agenda_id', 'announced_status', 'edited_at'])]
class Message extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
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

    /** @return BelongsTo<Agenda, $this> */
    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class);
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}

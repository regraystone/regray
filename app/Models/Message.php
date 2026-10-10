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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $conversation_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property int|null $last_read_message_id
 * @property Carbon|null $muted_at
 */
#[Fillable(['joined_at', 'last_read_message_id', 'muted_at'])]
class ConversationMember extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'conversation_members';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}

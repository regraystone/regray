<?php

namespace App\Models;

use App\Enums\ConversationMemberRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $conversation_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property int|null $last_read_message_id
 * @property Carbon|null $muted_at
 * @property ConversationMemberRole $role
 * @property int|null $last_delivered_message_id
 *
 * One user's place in a conversation.
 *
 * The table's key is (conversation_id, user_id), which Eloquent cannot express,
 * so `$primaryKey` is null and `save()` on a loaded row cannot work. Every write
 * after the insert goes through the query builder keyed by both columns: see
 * ConversationMemberRepository.
 */
#[Fillable(['conversation_id', 'user_id', 'joined_at', 'last_read_message_id', 'last_delivered_message_id', 'muted_at', 'role'])]
class ConversationMember extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'conversation_members';

    protected function casts(): array
    {
        return [
            'role' => ConversationMemberRole::class,
            'joined_at' => 'datetime',
            'muted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}

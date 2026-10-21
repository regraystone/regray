<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ConversationType $type
 * @property string|null $name
 * @property string|null $avatar_path
 * @property int|null $inquiry_id
 * @property string|null $direct_key
 * @property int|null $created_by
 * @property int|null $support_client_id
 * @property int|null $assigned_to
 * @property Carbon|null $assigned_at
 * @property int|null $pool_notified_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['type', 'name', 'avatar_path', 'inquiry_id', 'direct_key', 'created_by', 'support_client_id', 'assigned_to', 'assigned_at'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function supportClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isSupport(): bool
    {
        return $this->type === ConversationType::Support;
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to !== null;
    }

    /** @return HasMany<ConversationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}

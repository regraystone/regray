<?php

namespace App\Models;

use App\Data\StaffVisibility;
use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Keep only the inquiry group chats this staff member reads: those whose
     * inquiry they read (Inquiry::scopeVisibleTo()), member or not, and every
     * one for the admin and the owner. Every other conversation passes
     * untouched: direct chats, Regray Team threads, and groups bound to no
     * inquiry, which keep their own rules (membership, the support pool).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleToStaff(Builder $query, User $user): void
    {
        if (StaffVisibility::for($user)->everything) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where($this->qualifyColumn('type'), '!=', ConversationType::Group->value)
            ->orWhereNull($this->qualifyColumn('inquiry_id'))
            ->orWhereHas('inquiry', fn (Builder $inquiry) => $inquiry->visibleTo($user)));
    }

    /** A group chat bound to an inquiry, whose staff readers follow the inquiry. */
    public function isInquiryGroup(): bool
    {
        return $this->type === ConversationType::Group && $this->inquiry_id !== null;
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

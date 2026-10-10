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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['type', 'name', 'avatar_path', 'inquiry_id', 'direct_key', 'created_by'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}

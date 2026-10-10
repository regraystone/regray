<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property InquiryStatus $status
 * @property Carbon $status_changed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['reference', 'client_id', 'status', 'status_changed_at'])]
class Inquiry extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return HasMany<InquiryHandler, $this> */
    public function handlers(): HasMany
    {
        return $this->hasMany(InquiryHandler::class);
    }

    /** @return HasMany<InquiryStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(InquiryStatusHistory::class)->orderBy('changed_at');
    }

    /** @return HasMany<InquiryProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(InquiryProject::class);
    }

    /** @return HasOne<Conversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Agenda, $this> */
    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }
}

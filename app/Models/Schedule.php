<?php

namespace App\Models;

use App\Enums\BookedByType;
use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $booked_by
 * @property BookedByType $booked_by_type
 * @property int|null $inquiry_id
 * @property string $title
 * @property Carbon $arrival_at
 * @property ScheduleStatus $status
 * @property string|null $notes
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['booked_by', 'booked_by_type', 'inquiry_id', 'title', 'arrival_at', 'status', 'notes', 'approved_by', 'approved_at', 'cancelled_at', 'cancel_reason'])]
class Schedule extends Model
{
    protected function casts(): array
    {
        return [
            'booked_by_type' => BookedByType::class,
            'status' => ScheduleStatus::class,
            'arrival_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}

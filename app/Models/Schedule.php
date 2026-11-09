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
 * @property int|null $client_id Null for a direct (walk-in) client's inquiry visit.
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
 * @property string|null $change_reason Why it was last updated.
 * @property int $revision How many times it has changed since it was made.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * A client's booking is made by ScheduleService::createForBooking(): booked by
 * the client, with no inquiry, pending. A staff booking
 * (createForStaffBooking()) is for a client staff chose, and confirmed at once
 * with that staff member as its approver. Saving either posts a schedule_card
 * into the client's Regray thread (PostScheduleCard), as does every later
 * change: update, cancel, approve, served, did not attend, expired.
 *
 * Sending an inquiry creates its one schedule (with `inquiry_id` set),
 * through ScheduleService::createForInquiry(), confirmed from the start. It
 * follows its inquiry and none of the schedule actions apply to it.
 */
#[Fillable(['booked_by', 'booked_by_type', 'inquiry_id', 'title', 'arrival_at', 'status', 'notes', 'approved_by', 'approved_at', 'cancelled_at', 'cancel_reason', 'change_reason'])]
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
            'revision' => 'integer',
        ];
    }

    /** Whether it is an inquiry's visit, which follows its inquiry rather than the schedule actions. */
    public function isInquiryVisit(): bool
    {
        return $this->inquiry_id !== null;
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

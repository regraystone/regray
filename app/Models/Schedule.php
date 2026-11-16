<?php

namespace App\Models;

use App\Data\StaffVisibility;
use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int|null $approved_by Who last confirmed it. Kept when it is moved, so it says it was confirmed once.
 * @property Carbon|null $approved_at When it was last confirmed; set means the client has been told of it.
 * @property int|null $assigned_to The sales member who confirms it.
 * @property int|null $assigned_by Who assigned it to sales; kept through a decline.
 * @property Carbon|null $assigned_at
 * @property int|null $declined_by Who last declined it, until it is assigned again.
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property Carbon|null $sales_reminded_at When its sales member was last reminded to answer, since it was assigned.
 * @property Carbon|null $marketing_reminded_at When marketing was last reminded it still has no sales member.
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $change_reason Why it was last updated.
 * @property int $revision How many times it has changed since it was made.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * A client's booking is made by ScheduleService::createForBooking(): booked by
 * the client, with no inquiry, pending until marketing assigns it to a sales
 * member. A marketing booking (createForStaffBooking()) is for a client
 * marketing chose, and is assigned to a sales member at once. Either way it is
 * then awaiting sales until that sales member confirms it, or declines it
 * back to marketing (ScheduleChangeService). The client reads awaiting sales
 * as pending, and is told of a marketing booking only once it is confirmed
 * (PostScheduleCard, ScheduleNotificationService).
 *
 * Sending an inquiry creates its one schedule (with `inquiry_id` set),
 * through ScheduleService::createForInquiry(), confirmed from the start. It
 * follows its inquiry and none of the schedule actions apply to it.
 */
#[Fillable(['booked_by', 'booked_by_type', 'inquiry_id', 'title', 'arrival_at', 'status', 'notes', 'approved_by', 'approved_at', 'assigned_to', 'assigned_by', 'assigned_at', 'declined_by', 'declined_at', 'decline_reason', 'sales_reminded_at', 'marketing_reminded_at', 'cancelled_at', 'cancel_reason', 'change_reason'])]
class Schedule extends Model
{
    protected function casts(): array
    {
        return [
            'booked_by_type' => BookedByType::class,
            'status' => ScheduleStatus::class,
            'arrival_at' => 'datetime',
            'approved_at' => 'datetime',
            'assigned_at' => 'datetime',
            'declined_at' => 'datetime',
            'sales_reminded_at' => 'datetime',
            'marketing_reminded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    /** Whether it is an inquiry's visit, which follows its inquiry rather than the schedule actions. */
    public function isInquiryVisit(): bool
    {
        return $this->inquiry_id !== null;
    }

    /**
     * Whether the marketers handling it are due their one reminder to assign
     * it at `$now` (`schedules:remind`): still pending with nobody assigned
     * and still ahead, not yet reminded since it started waiting
     * (waitingForMarketingSince()), and either `marketing_reminder_hours`
     * since then or that close to its time, but at least an hour after it
     * started waiting. EloquentScheduleRepository::dueMarketingReminderIds()
     * asks the same of the table.
     */
    public function isDueMarketingReminder(CarbonInterface $now): bool
    {
        $hours = (int) config('schedule.marketing_reminder_hours');
        $since = $this->waitingForMarketingSince();

        return ! $this->isInquiryVisit()
            && $this->status === ScheduleStatus::Pending
            && $this->assigned_to === null
            && $this->marketing_reminded_at === null
            && $since !== null
            && $this->arrival_at->greaterThan($now)
            && $since->lessThanOrEqualTo($now->copy()->subHour())
            && ($since->lessThanOrEqualTo($now->copy()->subHours($hours))
                || $this->arrival_at->lessThanOrEqualTo($now->copy()->addHours($hours)));
    }

    /**
     * Since when it has waited for marketing to assign it: its last decline,
     * else when it was booked.
     */
    public function waitingForMarketingSince(): ?CarbonInterface
    {
        return $this->declined_at ?? $this->created_at;
    }

    /**
     * Whether its sales member is due their one reminder to answer at `$now`
     * (`schedules:remind`): still awaiting them and still ahead, not yet
     * reminded since it was assigned, and either `sales_reminder_hours` since
     * it was assigned or that close to its time, but at least an hour after it
     * was assigned. EloquentScheduleRepository::dueSalesReminderIds() asks
     * the same of the table.
     */
    public function isDueSalesReminder(CarbonInterface $now): bool
    {
        $hours = (int) config('schedule.sales_reminder_hours');

        return ! $this->isInquiryVisit()
            && $this->status === ScheduleStatus::AwaitingSales
            && $this->assigned_to !== null
            && $this->assigned_at !== null
            && $this->sales_reminded_at === null
            && $this->arrival_at->greaterThan($now)
            && $this->assigned_at->lessThanOrEqualTo($now->copy()->subHour())
            && ($this->assigned_at->lessThanOrEqualTo($now->copy()->subHours($hours))
                || $this->arrival_at->lessThanOrEqualTo($now->copy()->addHours($hours)));
    }

    /**
     * Only the visits this staff member reads. The admin and the owner read
     * every visit. Otherwise:
     *  - an inquiry's visit wherever its inquiry is read (Inquiry::scopeVisibleTo()),
     *    read-only for those who only read the inquiry;
     *  - marketing, a visit booked outside an inquiry that it booked or
     *    assigned, and a client's own booking in its queue (scopeInMarketingQueueOf());
     *  - sales, a visit booked outside an inquiry that is assigned to it.
     * Not for clients, who read their own.
     *
     * isVisibleTo() is the same rule for one loaded visit.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $visibility = StaffVisibility::for($user);

        if ($visibility->everything) {
            return;
        }

        $query->where(function (Builder $query) use ($visibility, $user): void {
            $query->whereHas('inquiry', fn (Builder $inquiry) => $inquiry->visibleTo($user));

            if ($visibility->handledVisits) {
                $query->orWhere(fn (Builder $visit) => $visit
                    ->whereNull($this->qualifyColumn('inquiry_id'))
                    ->where(fn (Builder $visit) => $visit
                        ->where($this->qualifyColumn('booked_by'), $user->id)
                        ->orWhere($this->qualifyColumn('assigned_by'), $user->id)
                        ->orWhere(fn (Builder $visit) => $visit->inMarketingQueueOf($user))));
            }

            if ($visibility->assignedVisits) {
                $query->orWhere(fn (Builder $visit) => $visit
                    ->whereNull($this->qualifyColumn('inquiry_id'))
                    ->where($this->qualifyColumn('assigned_to'), $user->id));
            }
        });
    }

    /**
     * A client's own bookings (outside an inquiry) that `$marketer` handles:
     * those of a client whose lead they hold; for a client with no lead, of one
     * whose Regray Team thread they hold; and for a client with neither, every
     * marketer's, a queue they share. marketingQueue() is the same rule from the
     * visit's side.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function inMarketingQueueOf(Builder $query, User $marketer): void
    {
        $query
            ->whereNull($this->qualifyColumn('inquiry_id'))
            ->where($this->qualifyColumn('booked_by_type'), BookedByType::Client->value)
            ->whereHas('client', fn (Builder $client) => $client->where(fn (Builder $client) => $client
                ->whereHas('leads', fn (Builder $lead) => $lead->where('marketing_user_id', $marketer->id))
                ->orWhere(fn (Builder $client) => $client
                    ->whereDoesntHave('leads')
                    ->where(fn (Builder $client) => $client
                        ->whereHas('supportConversation', fn (Builder $thread) => $thread->where('assigned_to', $marketer->id))
                        ->orWhereDoesntHave('supportConversation', fn (Builder $thread) => $thread->whereNotNull('assigned_to'))))));
    }

    /**
     * The marketers whose queue this client booking is in
     * (scopeInMarketingQueueOf()): the client's lead holder, else the holder of
     * their Regray Team thread, else every marketer. None for a booking staff
     * made or an inquiry's visit.
     *
     * @return Collection<int, User>
     */
    public function marketingQueue(): Collection
    {
        if ($this->isInquiryVisit() || $this->booked_by_type !== BookedByType::Client || $this->client_id === null) {
            return new Collection;
        }

        $holderId = Lead::query()->where('client_id', $this->client_id)->value('marketing_user_id')
            ?? Conversation::query()->where('support_client_id', $this->client_id)->value('assigned_to');

        if ($holderId !== null) {
            return User::query()->whereKey($holderId)->get();
        }

        return User::role(Role::Marketing->value)->get();
    }

    /** Whether this staff member reads this visit: scopeVisibleTo() for one row. */
    public function isVisibleTo(User $user): bool
    {
        $visibility = StaffVisibility::for($user);

        if ($visibility->everything) {
            return true;
        }

        if ($this->isInquiryVisit()) {
            $inquiry = $this->inquiry;

            return $inquiry !== null && $inquiry->isVisibleTo($user);
        }

        if ($visibility->assignedVisits && $this->assigned_to === $user->id) {
            return true;
        }

        if ($visibility->handledVisits && ($this->booked_by === $user->id || $this->assigned_by === $user->id)) {
            return true;
        }

        return $visibility->handledVisits
            && static::query()->whereKey($this->getKey())->inMarketingQueueOf($user)->exists();
    }

    /**
     * Whether the client has been told of it: they booked it themselves, or it
     * has been confirmed at least once. A marketing booking is announced to the
     * client only when sales confirms it.
     */
    public function isKnownToClient(): bool
    {
        return $this->booked_by_type === BookedByType::Client || $this->approved_at !== null;
    }

    /** @return BelongsTo<User, $this> The sales member who confirms it. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** Whose appointment it is, by name. */
    public function clientName(): ?string
    {
        return $this->client?->name;
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

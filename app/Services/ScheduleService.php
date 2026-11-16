<?php

namespace App\Services;

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleAction;
use App\Enums\ScheduleStatus;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Reads and writes schedules.
 *
 * Who sees what is decided here from the signed-in user, never from the
 * request: a client sees only their own visits, staff those
 * Schedule::scopeVisibleTo() gives them.
 */
class ScheduleService
{
    public const INQUIRY_CANCELLED_REASON = 'Inquiry cancelled';

    /** When a project with a date but no time of its own is booked for: the showroom opens at ten. */
    public const DEFAULT_ARRIVAL_TIME = '10:00';

    /** How many sales members the assign picker lists. */
    public const SALES_PICKER_LIMIT = 20;

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
    ) {}

    /**
     * Every visit the viewer may see arriving in `[$from, $to)`, any status: a
     * client's own, and for staff those Schedule::scopeVisibleTo() gives them.
     *
     * @return Collection<int, Schedule>
     */
    public function between(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->scheduleRepository->between($viewer, $from, $to);
    }

    /**
     * The viewer's next `$limit` pending or confirmed visits arriving on or
     * after `$since`, soonest first.
     *
     * @return Collection<int, Schedule>
     */
    public function nextOpenSince(User $viewer, CarbonInterface $since, int $limit): Collection
    {
        return $this->scheduleRepository->nextOpenSince($viewer, $since, $limit);
    }

    /**
     * The visit an inquiry is booked as: its one deadline visit.
     *
     * The client made it by sending the inquiry, so it is theirs and booked by
     * them; an inquiry staff opened for the client from a lead is booked by
     * that staff member (`$bookedBy`), and a direct (walk-in) client's has no
     * account to be for (`$client` null). It is confirmed from the start, with no
     * staff approver: an inquiry's visit needs no approval, and it follows its
     * inquiry rather than the schedule actions. The date and time are what was
     * typed, read in the studio's zone. Only a
     * visit asks for a time; any other project is booked for
     * DEFAULT_ARRIVAL_TIME on its date. The title and notes are the inquiry's
     * project name and location.
     */
    public function createForInquiry(Inquiry $inquiry, ?User $client, string $date, ?string $time, ?User $bookedBy = null): Schedule
    {
        return $this->scheduleRepository->create([
            'client_id' => $client?->id,
            'client_record_id' => $inquiry->client_record_id,
            'booked_by' => ($bookedBy ?? $client)?->id,
            'booked_by_type' => $bookedBy === null ? BookedByType::Client : BookedByType::Staff,
            'inquiry_id' => $inquiry->id,
            'title' => $inquiry->name,
            'arrival_at' => self::arrivalAt($date, $time ?? self::DEFAULT_ARRIVAL_TIME)->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::Confirmed,
            'notes' => self::inquiryNotes($inquiry),
            'approved_at' => Date::now(),
        ]);
    }

    /**
     * A client's own booking from the Book an appointment page.
     *
     * Theirs and booked by them, with no inquiry, pending until marketing
     * assigns it to a sales member, who then confirms it. The purpose is its
     * title. `$arrivalAt` is read in the studio's zone and stored in the app's.
     */
    public function createForBooking(User $client, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        return $this->scheduleRepository->create([
            'client_id' => $client->id,
            'booked_by' => $client->id,
            'booked_by_type' => BookedByType::Client,
            'inquiry_id' => null,
            'title' => $purpose,
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::Pending,
            'notes' => $notes,
        ]);
    }

    /**
     * An appointment marketing books for a client it chose: a client account
     * (`User`), or a direct (walk-in) client record with no account (`Client`),
     * who is never told. Booked by the marketer, and assigned at once to
     * `$sales`, so it is awaiting sales until that sales member confirms it.
     * Not confirmed yet, and not yet announced to the client
     * (Schedule::isKnownToClient()).
     */
    public function createForStaffBooking(User $staff, User|Client $client, User $sales, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        return $this->scheduleRepository->create([
            'client_id' => $client instanceof User ? $client->id : null,
            'client_record_id' => $client instanceof Client ? $client->id : null,
            'booked_by' => $staff->id,
            'booked_by_type' => BookedByType::Staff,
            'inquiry_id' => null,
            'title' => $purpose,
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::AwaitingSales,
            'notes' => $notes,
            'assigned_to' => $sales->id,
            'assigned_by' => $staff->id,
            'assigned_at' => Date::now(),
        ]);
    }

    /**
     * The sales members an appointment may be assigned to, matching a search
     * by name or email, by name, each with `open_appointment_count`: how many
     * appointments assigned to them are still to happen (awaiting them or
     * confirmed, not yet past). Informational only.
     *
     * @return Collection<int, User>
     */
    public function assignableSales(?string $search): Collection
    {
        $search = $search === null ? '' : trim($search);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Sales->value))
            ->whereDoesntHave('roles', fn (Builder $role) => $role->where('name', Role::Client->value))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ILIKE', $pattern)
                ->orWhere('email', 'ILIKE', $pattern)))
            ->withCount(['assignedSchedules as open_appointment_count' => fn (Builder $schedule) => $schedule
                ->whereIn('status', [ScheduleStatus::AwaitingSales->value, ScheduleStatus::Confirmed->value])
                ->where('arrival_at', '>=', Date::now())])
            ->orderBy('name')
            ->limit(self::SALES_PICKER_LIMIT)
            ->get(['users.id', 'users.name']);
    }

    /** Whether `$user` may be given an appointment to confirm: a sales member, not a client. */
    public static function isAssignableSales(User $user): bool
    {
        return $user->hasRole(Role::Sales->value) && ! $user->isClient();
    }

    /**
     * Bring an inquiry's visit in line with an edit of the inquiry: its title,
     * its notes and, when it moved, its arrival.
     *
     * Does not decide whether the arrival may move. The caller asks
     * deadlineLockedReason() first, because only it knows which field the
     * refusal belongs to.
     */
    public function updateForInquiry(Schedule $schedule, Inquiry $inquiry, CarbonImmutable $arrivalAt): void
    {
        $this->scheduleRepository->update($schedule, [
            'title' => $inquiry->name,
            'notes' => self::inquiryNotes($inquiry),
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
        ]);
    }

    /**
     * Where an edited inquiry's visit should now arrive.
     *
     * A visit's own time when the project has one. Any other project keeps the
     * time of day its schedule already has, so moving only the date never moves
     * the hour, and an unchanged date compares equal to the stored arrival.
     */
    public function arrivalForInquiry(?Schedule $current, string $date, ?string $time): CarbonImmutable
    {
        if ($time !== null) {
            return self::arrivalAt($date, $time);
        }

        $timeOfDay = $current?->arrival_at->setTimezone(self::timezone())->format('H:i') ?? self::DEFAULT_ARRIVAL_TIME;

        return self::arrivalAt($date, $timeOfDay);
    }

    /**
     * Why this visit's date and time can no longer be changed from the inquiry,
     * or null while they still can.
     *
     * An inquiry's visit is confirmed from the start and follows its inquiry, so
     * pending or confirmed never locks it: the inquiry's own rule does, once the
     * client approves the quotation (InquiryStatusRules::isEditable()).
     */
    public function deadlineLockedReason(Schedule $schedule): ?string
    {
        return match ($schedule->status) {
            ScheduleStatus::Pending, ScheduleStatus::AwaitingSales, ScheduleStatus::Confirmed, ScheduleStatus::Expired => null,
            ScheduleStatus::Served => 'This visit has already taken place, so its date and time can no longer be changed.',
            ScheduleStatus::Cancelled => 'This visit was cancelled, so its date and time can no longer be changed here.',
        };
    }

    /**
     * Why `$action` cannot be done to this appointment now, or null when it can.
     *
     * The appointment's state alone, for every actor: who may act at all is
     * SchedulePolicy's, and the super-admin, who passes the policy, is still held
     * to this. ScheduleResource asks it for the buttons, and ScheduleChangeService
     * again under the row lock, so a button never offers what the save refuses.
     */
    public function refusalFor(Schedule $schedule, ScheduleAction $action, ?CarbonInterface $now = null): ?string
    {
        if ($schedule->isInquiryVisit()) {
            return 'This visit belongs to an inquiry, so it changes with the inquiry.';
        }

        $hasArrived = $schedule->arrival_at->lessThanOrEqualTo($now ?? Date::now());
        $status = $schedule->status;

        return match ($action) {
            ScheduleAction::Update, ScheduleAction::Cancel => match (true) {
                ! $status->isOpen() => self::closedReason($status),
                $hasArrived => 'This appointment\'s time has passed, so it can no longer be changed.',
                default => null,
            },
            // Assigned again while it waits on sales too, as when the sales member it went to does not answer.
            ScheduleAction::Assign => match (true) {
                $status === ScheduleStatus::Confirmed => 'This appointment is already confirmed.',
                ! $status->isUnconfirmed() => self::closedReason($status),
                $hasArrived => 'This appointment\'s time has passed, so it can no longer be assigned.',
                default => null,
            },
            ScheduleAction::Confirm, ScheduleAction::Decline => match (true) {
                $status === ScheduleStatus::Confirmed => 'This appointment is already confirmed.',
                $status === ScheduleStatus::Pending => 'This appointment is not assigned to sales yet.',
                $status !== ScheduleStatus::AwaitingSales => self::closedReason($status),
                $hasArrived => 'This appointment\'s time has passed, so it can no longer be '.($action === ScheduleAction::Confirm ? 'confirmed.' : 'declined.'),
                default => null,
            },
            ScheduleAction::Serve, ScheduleAction::NoShow => match (true) {
                $status->isUnconfirmed() => 'Sales must confirm this appointment before you mark whether the client came.',
                $status !== ScheduleStatus::Confirmed => self::closedReason($status),
                ! $hasArrived => 'You can mark this once its time has come.',
                default => null,
            },
        };
    }

    private static function closedReason(ScheduleStatus $status): string
    {
        return match ($status) {
            ScheduleStatus::Served => 'This appointment has already been served, so it can no longer be changed.',
            ScheduleStatus::Cancelled => 'This appointment was cancelled, so it can no longer be changed.',
            ScheduleStatus::Expired => 'This appointment expired before it was confirmed, so it can no longer be changed. Book a new one instead.',
            default => 'This appointment can no longer be changed.',
        };
    }

    /** "Inquiry INQ-1048 · Kitchen counters · Makati", or "· Pickup" where there is no location. */
    private static function inquiryNotes(Inquiry $inquiry): string
    {
        return "Inquiry {$inquiry->reference} · {$inquiry->name} · ".($inquiry->location ?? 'Pickup');
    }

    /** Cancel the inquiry's open visits along with it. */
    public function cancelForInquiry(Inquiry $inquiry, CarbonInterface $cancelledAt): void
    {
        $this->scheduleRepository->cancelOpenForInquiry($inquiry, $cancelledAt, self::INQUIRY_CANCELLED_REASON);
    }

    /**
     * A `Y-m-d` date and an `H:i` time, read in the studio's zone.
     *
     * Convert it to the app's zone before writing it or binding it in a query:
     * Eloquent formats a date without its offset.
     */
    public static function arrivalAt(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::timezone());
    }

    /**
     * The first day `$user` may book an appointment for, by the studio's clock:
     * tomorrow for a client, today for staff. Times already past are refused
     * separately.
     */
    public static function earliestBookableDate(User $user): CarbonImmutable
    {
        $today = Date::now(self::timezone())->toImmutable()->startOfDay();

        return $user->isClient() ? $today->addDay() : $today;
    }

    public static function timezone(): string
    {
        return config('schedule.timezone');
    }
}

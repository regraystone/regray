<?php

namespace App\Services;

use App\Enums\BookedByType;
use App\Enums\ScheduleAction;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Reads and writes schedules.
 *
 * Who sees what is decided here from the signed-in user, never from the
 * request: a client sees only their own visits, every staff role sees all.
 */
class ScheduleService
{
    public const INQUIRY_CANCELLED_REASON = 'Inquiry cancelled';

    /** When a project with a date but no time of its own is booked for: the showroom opens at ten. */
    public const DEFAULT_ARRIVAL_TIME = '10:00';

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
    ) {}

    /**
     * Every visit the viewer may see arriving in `[$from, $to)`, any status.
     *
     * @return Collection<int, Schedule>
     */
    public function between(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->scheduleRepository->between($this->clientScope($viewer), $from, $to);
    }

    /**
     * The viewer's next `$limit` pending or confirmed visits arriving on or
     * after `$since`, soonest first.
     *
     * @return Collection<int, Schedule>
     */
    public function nextOpenSince(User $viewer, CarbonInterface $since, int $limit): Collection
    {
        return $this->scheduleRepository->nextOpenSince($this->clientScope($viewer), $since, $limit);
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
     * Theirs and booked by them, with no inquiry, pending until Marketing or
     * Sales approve it. The purpose is its title. `$arrivalAt` is read in the
     * studio's zone and stored in the app's.
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
     * An appointment staff book for a client they chose: the client's, booked by
     * the staff member, and confirmed at once with that staff member as its
     * approver, since staff booking it is the studio agreeing to it.
     */
    public function createForStaffBooking(User $staff, User $client, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        return $this->scheduleRepository->create([
            'client_id' => $client->id,
            'booked_by' => $staff->id,
            'booked_by_type' => BookedByType::Staff,
            'inquiry_id' => null,
            'title' => $purpose,
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::Confirmed,
            'notes' => $notes,
            'approved_by' => $staff->id,
            'approved_at' => Date::now(),
        ]);
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
            ScheduleStatus::Pending, ScheduleStatus::Confirmed, ScheduleStatus::Expired => null,
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

        return match ($action) {
            ScheduleAction::Update, ScheduleAction::Cancel => match (true) {
                ! $schedule->status->isOpen() => self::closedReason($schedule->status),
                $hasArrived => 'This appointment\'s time has passed, so it can no longer be changed.',
                default => null,
            },
            ScheduleAction::Approve => match (true) {
                $schedule->status === ScheduleStatus::Confirmed => 'This appointment is already approved.',
                $schedule->status !== ScheduleStatus::Pending => self::closedReason($schedule->status),
                $hasArrived => 'This appointment\'s time has passed, so it can no longer be approved.',
                default => null,
            },
            ScheduleAction::Serve, ScheduleAction::NoShow => match (true) {
                $schedule->status === ScheduleStatus::Pending => 'Approve this appointment before marking whether the client came.',
                $schedule->status !== ScheduleStatus::Confirmed => self::closedReason($schedule->status),
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
            ScheduleStatus::Expired => 'This appointment expired before it was approved, so it can no longer be changed. Book a new one instead.',
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

    /** The client whose visits the viewer is limited to, or null for staff. */
    private function clientScope(User $viewer): ?User
    {
        return $viewer->isClient() ? $viewer : null;
    }
}

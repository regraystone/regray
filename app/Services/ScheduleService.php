<?php

namespace App\Services;

use App\Enums\BookedByType;
use App\Enums\InquiryStatus;
use App\Enums\QuoteTab;
use App\Enums\Role;
use App\Enums\ScheduleAction;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleStatus;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

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
        public readonly InquiryRepositoryInterface $inquiryRepository,
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
     * project name and the city it goes to.
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
            'kind' => ScheduleKind::ShowroomVisit,
            'title' => $purpose,
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::Pending,
            'notes' => $notes,
        ]);
    }

    /**
     * A client's installation or delivery (`$kind`) for one of their accepted
     * orders, from the Book an appointment page.
     *
     * Theirs and booked by them. It goes straight to the sales member handling
     * the order (orderSales()), awaiting their confirmation, with no marketing
     * step; an order no sales member handles yet waits for marketing to
     * assign it, as a showroom visit does. Its title says what it is and for
     * which project. `$arrivalAt` is read in the studio's zone and stored in
     * the app's.
     */
    public function createForOrder(User $client, Inquiry $order, ScheduleKind $kind, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $sales = $this->orderSales($order);

        return $this->scheduleRepository->create([
            'client_id' => $client->id,
            'booked_by' => $client->id,
            'booked_by_type' => BookedByType::Client,
            'inquiry_id' => null,
            'kind' => $kind,
            'order_id' => $order->id,
            // Kept within the purpose's 80 characters, which an update checks again.
            'title' => Str::limit("{$kind->label()}: {$order->name}", 79, '…'),
            'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
            'status' => $sales === null ? ScheduleStatus::Pending : ScheduleStatus::AwaitingSales,
            'notes' => $notes,
            'assigned_to' => $sales?->id,
            'assigned_at' => $sales === null ? null : Date::now(),
        ]);
    }

    /**
     * The sales member who confirms an order's installation or delivery: the
     * first assigned to it who is still in sales, or null.
     */
    public function orderSales(Inquiry $order): ?User
    {
        return User::query()
            ->role(Role::Sales->value)
            ->join('inquiry_handlers', 'inquiry_handlers.user_id', '=', 'users.id')
            ->where('inquiry_handlers.inquiry_id', $order->id)
            ->orderBy('inquiry_handlers.assigned_at')
            ->orderBy('users.id')
            ->select('users.*')
            ->get()
            ->first(fn (User $user): bool => self::isAssignableSales($user));
    }

    /**
     * The statuses an order is at when the client may book its installation
     * or delivery: won by sales and paid, which starts it at order received,
     * through every logistics stage (the Quotations page's Accepted tab),
     * until it is closed. A won order with no payment yet waits.
     *
     * @return list<InquiryStatus>
     */
    public static function orderStatuses(): array
    {
        return array_values(array_filter(
            QuoteTab::Accepted->statuses(),
            fn (InquiryStatus $status): bool => $status !== InquiryStatus::Closed,
        ));
    }

    /**
     * The client's accepted orders the Book an appointment page offers, each
     * with the kinds it may still be booked for: an installation for a service
     * the team installs, a delivery for one they chose to have delivered, and
     * neither while one of that kind is still open. An order with neither left
     * is not listed.
     *
     * @return list<array{reference: string, name: string, service: string|null, kinds: list<string>}>
     */
    public function ordersToSchedule(User $client): array
    {
        $orders = [];

        foreach ($this->inquiryRepository->ordersForScheduling($client, ...self::orderStatuses()) as $order) {
            $kinds = array_values(array_filter(
                [ScheduleKind::Installation, ScheduleKind::Delivery],
                fn (ScheduleKind $kind): bool => $order->takes($kind)
                    && ! $order->orderSchedules->contains(fn (Schedule $schedule): bool => $schedule->kind === $kind),
            ));

            if ($kinds !== []) {
                $orders[] = [
                    'reference' => $order->reference,
                    'name' => $order->name,
                    'service' => $order->service?->name,
                    'kinds' => array_map(fn (ScheduleKind $kind): string => $kind->value, $kinds),
                ];
            }
        }

        return $orders;
    }

    /**
     * Why the client may not book `$kind` for their order `$order` (null when
     * it is none of their accepted orders), or null when they may: it must
     * take that kind and have none of it open already.
     */
    public function orderRefusal(?Inquiry $order, ScheduleKind $kind): ?string
    {
        if ($order === null) {
            return 'Choose one of your accepted orders';
        }

        if (! $order->takes($kind)) {
            return $kind === ScheduleKind::Installation
                ? 'This order has no installation. Choose another order.'
                : 'This order is not for delivery. Choose another order.';
        }

        $alreadyBooked = $order->orderSchedules()
            ->where('kind', $kind->value)
            ->whereIn('status', ScheduleStatus::open())
            ->exists();

        return $alreadyBooked
            ? 'This order already has '.($kind === ScheduleKind::Installation ? 'an installation' : 'a delivery').' booked. You can change it from your schedule.'
            : null;
    }

    /** The client's accepted order `$reference`, with its service, or null. */
    public function findOrder(User $client, string $reference): ?Inquiry
    {
        return $this->inquiryRepository->findOrderForScheduling($client, $reference, ...self::orderStatuses());
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
            'kind' => ScheduleKind::ShowroomVisit,
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

    /**
     * For each of `$salesIds` with one, the earliest appointment of theirs
     * that a visit at `$arrivalAt` would overlap: closer to it than
     * `schedule.visit_minutes`. Theirs are those assigned to them (awaiting
     * them or confirmed) and the confirmed visits of the inquiries they handle
     * (`inquiry_handlers`) or opened. `$exceptId` is the appointment being
     * assigned or confirmed, which never clashes with itself. A warning for
     * the sales picker and for confirming, never a refusal.
     *
     * @param  list<int>  $salesIds
     * @return array<int, Schedule> keyed by sales member id
     */
    public function salesClashes(array $salesIds, CarbonImmutable $arrivalAt, ?int $exceptId = null): array
    {
        if ($salesIds === []) {
            return [];
        }

        $window = max(1, (int) config('schedule.visit_minutes')) - 1;
        $at = $arrivalAt->setTimezone(config('app.timezone'));
        $around = [$at->subMinutes($window)->subSeconds(59), $at->addMinutes($window)->addSeconds(59)];

        $assigned = Schedule::query()
            ->select(['id', 'title', 'arrival_at', 'assigned_to as sales_id'])
            ->whereIn('assigned_to', $salesIds)
            ->whereIn('status', [ScheduleStatus::AwaitingSales->value, ScheduleStatus::Confirmed->value])
            ->whereBetween('arrival_at', $around)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId));

        // An inquiry's visit is never assigned: it is theirs through its inquiry.
        $handled = Schedule::query()
            ->join('inquiry_handlers', 'inquiry_handlers.inquiry_id', '=', 'schedules.inquiry_id')
            ->select(['schedules.id', 'schedules.title', 'schedules.arrival_at', 'inquiry_handlers.user_id as sales_id'])
            ->whereIn('inquiry_handlers.user_id', $salesIds)
            ->where('schedules.status', ScheduleStatus::Confirmed->value)
            ->whereBetween('schedules.arrival_at', $around)
            ->when($exceptId !== null, fn (Builder $query) => $query->where('schedules.id', '!=', $exceptId));

        $opened = Schedule::query()
            ->join('inquiries', 'inquiries.id', '=', 'schedules.inquiry_id')
            ->select(['schedules.id', 'schedules.title', 'schedules.arrival_at', 'inquiries.created_by as sales_id'])
            ->whereIn('inquiries.created_by', $salesIds)
            ->where('schedules.status', ScheduleStatus::Confirmed->value)
            ->whereBetween('schedules.arrival_at', $around)
            ->when($exceptId !== null, fn (Builder $query) => $query->where('schedules.id', '!=', $exceptId));

        return $assigned->get()
            ->concat($handled->get())
            ->concat($opened->get())
            ->sortBy([['arrival_at', 'asc'], ['id', 'asc']])
            ->unique(fn (Schedule $schedule): int => (int) $schedule->getAttribute('sales_id'))
            ->keyBy(fn (Schedule $schedule): int => (int) $schedule->getAttribute('sales_id'))
            ->all();
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

    /** "Inquiry INQ-1048 · Kitchen counters · City of Makati", or "· Pickup" where there is no address. */
    private static function inquiryNotes(Inquiry $inquiry): string
    {
        return "Inquiry {$inquiry->reference} · {$inquiry->name} · ".($inquiry->placeLabel() ?? 'Pickup');
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

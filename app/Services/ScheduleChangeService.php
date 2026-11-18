<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Enums\ScheduleAction;
use App\Enums\ScheduleChangeKind;
use App\Enums\ScheduleStatus;
use App\Events\ScheduleChanged;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What happens to an appointment after it is booked: updated, cancelled,
 * assigned to sales, confirmed or declined by sales, served, marked as not
 * attended, or expired.
 *
 * Every change runs on the locked row (so two people confirming at once cannot
 * both succeed), is refused with ScheduleService::refusalFor()'s reason when the
 * appointment's state does not allow it, bumps the appointment's revision,
 * writes one audit entry with the before and after values and the reason, and
 * announces itself with ScheduleChanged once committed. Who may act at all is
 * SchedulePolicy's, checked before any of this runs.
 */
class ScheduleChangeService
{
    /** The cancel reason "Did not attend" leaves, so the list says what happened. */
    public const NO_SHOW_REASON = 'Did not attend';

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
        public readonly ScheduleService $scheduleService,
        public readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    /**
     * Move an appointment, or change its purpose or notes, saying why.
     *
     * An appointment sales has agreed to, or is asked to, must be agreed to
     * again when it moves, or whenever the client changes it: it goes back to
     * awaiting its sales member, or to pending for marketing when it has none.
     * A pending one stays pending.
     */
    public function update(Schedule $schedule, User $by, string $purpose, CarbonImmutable $arrivalAt, ?string $notes, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Update, $by, $ipAddress, $userAgent, function (Schedule $locked) use ($by, $purpose, $arrivalAt, $notes, $reason): array {
            $attributes = [
                'title' => $purpose,
                'arrival_at' => $arrivalAt->setTimezone(config('app.timezone')),
                'notes' => $notes,
                'change_reason' => $reason,
            ];

            $moved = ! $locked->arrival_at->equalTo($arrivalAt);
            $agreed = $locked->status === ScheduleStatus::Confirmed || $locked->status === ScheduleStatus::AwaitingSales;

            if ($agreed && ($moved || $by->isClient())) {
                $attributes['status'] = $locked->assigned_to !== null ? ScheduleStatus::AwaitingSales : ScheduleStatus::Pending;

                // Back to its sales member: asked anew, so its one reminder counts from now.
                if ($locked->assigned_to !== null) {
                    $attributes['assigned_at'] = Date::now();
                    $attributes['sales_reminded_at'] = null;
                }
            }

            return [$attributes, ScheduleChangeKind::Rescheduled, AuditEvent::Updated, $reason];
        });
    }

    /** Cancel an appointment, saying why. */
    public function cancel(Schedule $schedule, User $by, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Cancel, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Cancelled, 'cancelled_at' => Date::now(), 'cancel_reason' => $reason],
            ScheduleChangeKind::Cancelled,
            AuditEvent::StatusChanged,
            $reason,
        ]);
    }

    /**
     * Assign a pending appointment (or one still awaiting sales) to `$sales`,
     * by `$by`: it is awaiting that sales member until they confirm or decline
     * it. Any earlier decline is cleared.
     *
     * @throws ValidationException When `$sales` is not a sales member.
     */
    public function assign(Schedule $schedule, User $by, User $sales, ?string $ipAddress, ?string $userAgent): Schedule
    {
        if (! ScheduleService::isAssignableSales($sales)) {
            throw ValidationException::withMessages(['salesId' => 'Choose a sales member to confirm this appointment']);
        }

        return $this->change($schedule, ScheduleAction::Assign, $by, $ipAddress, $userAgent, fn (): array => [
            [
                'status' => ScheduleStatus::AwaitingSales,
                'assigned_to' => $sales->id,
                'assigned_by' => $by->id,
                'assigned_at' => Date::now(),
                'sales_reminded_at' => null,
                'declined_by' => null,
                'declined_at' => null,
                'decline_reason' => null,
            ],
            ScheduleChangeKind::Assigned,
            AuditEvent::StatusChanged,
            null,
        ]);
    }

    /** The sales member it is assigned to confirms it: it is confirmed, with `$by` as its approver. */
    public function confirm(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Confirm, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Confirmed, 'approved_by' => $by->id, 'approved_at' => Date::now()],
            ScheduleChangeKind::Confirmed,
            AuditEvent::StatusChanged,
            null,
        ]);
    }

    /**
     * The sales member it is assigned to declines it, saying why: it is pending
     * again, assigned to nobody, for marketing to assign it again. Who assigned
     * it is kept, so they still read it.
     */
    public function decline(Schedule $schedule, User $by, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Decline, $by, $ipAddress, $userAgent, fn (): array => [
            [
                'status' => ScheduleStatus::Pending,
                'assigned_to' => null,
                'assigned_at' => null,
                'declined_by' => $by->id,
                'declined_at' => Date::now(),
                'decline_reason' => $reason,
                // Back with marketing: its one reminder counts from now.
                'marketing_reminded_at' => null,
            ],
            ScheduleChangeKind::Declined,
            AuditEvent::StatusChanged,
            $reason,
        ]);
    }

    /** The client came: a confirmed appointment whose time has come is served. */
    public function serve(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Serve, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Served],
            ScheduleChangeKind::Served,
            AuditEvent::StatusChanged,
            null,
        ]);
    }

    /** The client did not come: the appointment is cancelled with NO_SHOW_REASON. */
    public function noShow(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::NoShow, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Cancelled, 'cancelled_at' => Date::now(), 'cancel_reason' => self::NO_SHOW_REASON],
            ScheduleChangeKind::Cancelled,
            AuditEvent::StatusChanged,
            self::NO_SHOW_REASON,
        ]);
    }

    /**
     * Expire one appointment still pending or awaiting sales when its time has
     * passed, by nobody (`schedules:expire`). False when it was confirmed,
     * cancelled or moved meanwhile, which leaves it alone.
     */
    public function expire(int $scheduleId): bool
    {
        return DB::transaction(function () use ($scheduleId): bool {
            $locked = $this->scheduleRepository->findForUpdate($scheduleId);

            if ($locked->isInquiryVisit() || ! $locked->status->isUnconfirmed() || $locked->arrival_at->isFuture()) {
                return false;
            }

            $this->apply($locked, null, null, null, [
                ['status' => ScheduleStatus::Expired],
                ScheduleChangeKind::Expired,
                AuditEvent::StatusChanged,
                null,
            ]);

            return true;
        });
    }

    /**
     * Run one change on the locked row, refusing it when the appointment's state
     * does not allow it now.
     *
     * @param  Closure(Schedule): array{0: array<string, mixed>, 1: ScheduleChangeKind, 2: AuditEvent, 3: ?string}  $decide
     */
    private function change(Schedule $schedule, ScheduleAction $action, User $by, ?string $ipAddress, ?string $userAgent, Closure $decide): Schedule
    {
        return DB::transaction(function () use ($schedule, $action, $by, $ipAddress, $userAgent, $decide): Schedule {
            // A visit this staff member does not read is not found: a 404, as if it did not exist.
            $locked = $this->scheduleRepository->findForUpdate($schedule->id, $by);
            $refusal = $this->scheduleService->refusalFor($locked, $action);

            if ($refusal !== null) {
                throw ValidationException::withMessages(['schedule' => $refusal]);
            }

            // Reassigned to somebody else meanwhile: only its sales member confirms or declines it.
            if ($action->isForAssignedSales() && $locked->assigned_to !== $by->id && ! $by->hasRole(Role::superAdmin()->value)) {
                throw ValidationException::withMessages(['schedule' => 'This appointment is now assigned to someone else.']);
            }

            $this->apply($locked, $by, $ipAddress, $userAgent, $decide($locked));

            return $locked;
        });
    }

    /**
     * Write the change, its audit entry and its announcement.
     *
     * @param  array{0: array<string, mixed>, 1: ScheduleChangeKind, 2: AuditEvent, 3: ?string}  $decision
     */
    private function apply(Schedule $locked, ?User $by, ?string $ipAddress, ?string $userAgent, array $decision): void
    {
        [$attributes, $kind, $event, $reason] = $decision;
        $before = $this->auditSnapshot($locked);

        $this->scheduleRepository->update($locked, [...$attributes, 'revision' => $locked->revision + 1]);

        $this->auditLogRepository->record(
            $by?->id,
            $event,
            $locked,
            $before,
            [...$this->auditSnapshot($locked), 'reason' => $reason],
            $ipAddress,
            $userAgent,
        );

        event(new ScheduleChanged($locked, $kind, $locked->revision, $locked->arrival_at->toIso8601String(), $reason, $by?->id));
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(Schedule $schedule): array
    {
        return [
            'title' => $schedule->title,
            'arrival_at' => $schedule->arrival_at->toIso8601String(),
            'notes' => $schedule->notes,
            'status' => $schedule->status->value,
            'approved_by' => $schedule->approved_by,
            'assigned_to' => $schedule->assigned_to,
            'assigned_by' => $schedule->assigned_by,
            'decline_reason' => $schedule->decline_reason,
            'cancel_reason' => $schedule->cancel_reason,
            'change_reason' => $schedule->change_reason,
        ];
    }
}

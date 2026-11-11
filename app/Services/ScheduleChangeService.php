<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\ScheduleAction;
use App\Enums\ScheduleCardStatus;
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
 * approved, served, marked as not attended, or expired.
 *
 * Every change runs on the locked row (so two people approving at once cannot
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
     * A client who moves a confirmed appointment sends it back to pending for
     * the team to approve again. A staff update leaves its status as it is.
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

            if ($by->isClient() && $locked->status === ScheduleStatus::Confirmed) {
                $attributes += ['status' => ScheduleStatus::Pending, 'approved_by' => null, 'approved_at' => null];
            }

            return [$attributes, ScheduleCardStatus::Rescheduled, AuditEvent::Updated, $reason];
        });
    }

    /** Cancel an appointment, saying why. */
    public function cancel(Schedule $schedule, User $by, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Cancel, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Cancelled, 'cancelled_at' => Date::now(), 'cancel_reason' => $reason],
            ScheduleCardStatus::Cancelled,
            AuditEvent::StatusChanged,
            $reason,
        ]);
    }

    /** Approve a pending appointment: it is confirmed, with `$by` as its approver. */
    public function approve(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Approve, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Confirmed, 'approved_by' => $by->id, 'approved_at' => Date::now()],
            ScheduleCardStatus::Confirmed,
            AuditEvent::StatusChanged,
            null,
        ]);
    }

    /** The client came: a confirmed appointment whose time has come is served. */
    public function serve(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::Serve, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Served],
            ScheduleCardStatus::Served,
            AuditEvent::StatusChanged,
            null,
        ]);
    }

    /** The client did not come: the appointment is cancelled with NO_SHOW_REASON. */
    public function noShow(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->change($schedule, ScheduleAction::NoShow, $by, $ipAddress, $userAgent, fn (): array => [
            ['status' => ScheduleStatus::Cancelled, 'cancelled_at' => Date::now(), 'cancel_reason' => self::NO_SHOW_REASON],
            ScheduleCardStatus::Cancelled,
            AuditEvent::StatusChanged,
            self::NO_SHOW_REASON,
        ]);
    }

    /**
     * Expire one appointment still pending when its time has passed, by nobody
     * (`schedules:expire`). False when it was approved, cancelled or moved
     * meanwhile, which leaves it alone.
     */
    public function expire(int $scheduleId): bool
    {
        return DB::transaction(function () use ($scheduleId): bool {
            $locked = $this->scheduleRepository->findForUpdate($scheduleId);

            if ($locked->isInquiryVisit() || $locked->status !== ScheduleStatus::Pending || $locked->arrival_at->isFuture()) {
                return false;
            }

            $this->apply($locked, null, null, null, [
                ['status' => ScheduleStatus::Expired],
                ScheduleCardStatus::Expired,
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
     * @param  Closure(Schedule): array{0: array<string, mixed>, 1: ScheduleCardStatus, 2: AuditEvent, 3: ?string}  $decide
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

            $this->apply($locked, $by, $ipAddress, $userAgent, $decide($locked));

            return $locked;
        });
    }

    /**
     * Write the change, its audit entry and its announcement.
     *
     * @param  array{0: array<string, mixed>, 1: ScheduleCardStatus, 2: AuditEvent, 3: ?string}  $decision
     */
    private function apply(Schedule $locked, ?User $by, ?string $ipAddress, ?string $userAgent, array $decision): void
    {
        [$attributes, $card, $event, $reason] = $decision;
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

        event(new ScheduleChanged($locked, $card, $locked->revision, $locked->arrival_at->toIso8601String(), $reason));
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
            'cancel_reason' => $schedule->cancel_reason,
            'change_reason' => $schedule->change_reason,
        ];
    }
}

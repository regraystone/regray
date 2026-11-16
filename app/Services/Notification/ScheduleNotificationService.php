<?php

namespace App\Services\Notification;

use App\Enums\BookedByType;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\ScheduleChangeKind;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Appointment notifications, each a short sentence: "you" to whoever it is
 * about, a name to everyone else.
 *
 * Only those who read the appointment hear of it (its watchers): the owner and
 * the admin; the marketers handling it (who booked or assigned it, and for a
 * client's own booking, those whose queue it is in, Schedule::marketingQueue());
 * and the sales member it is assigned to. Whoever made a change is never told
 * of it as news; they are told it worked, or nothing, as the page already says.
 *
 * - A client books: the client is told it was sent, and marketing and the
 *   admins that it waits to be assigned.
 * - Marketing books: the sales member it is assigned to is asked to confirm it,
 *   the admins are told. The client hears nothing until it is confirmed.
 * - Assigned: the sales member is asked to confirm it.
 * - Confirmed: the client is told, and marketing and the admins.
 * - Declined: marketing and the admins, with the reason, to assign it again.
 * - Updated or cancelled (marking one as not attended is a cancel): whoever
 *   did it is told it worked, the client what changed once they know of the
 *   appointment (Schedule::isKnownToClient()), and the other watchers.
 * - Served or expired: the client (expired, once they know of it) and the
 *   watchers.
 *
 * Never for an inquiry's visit, which follows its inquiry. A walk-in, with no
 * account, is never told; staff are, by the walk-in's name.
 */
class ScheduleNotificationService
{
    public function __construct(
        public readonly NotificationService $notifications,
    ) {}

    public function booked(Schedule $schedule): void
    {
        if ($schedule->isInquiryVisit()) {
            return;
        }

        $account = $this->clientAccount($schedule);
        $name = $this->clientName($schedule);
        $date = $this->date($schedule->arrival_at);

        if ($schedule->booked_by_type === BookedByType::Client && $account !== null) {
            $this->send([$account], $schedule, "Your appointment request for {$date} has been sent.");
            $this->send($this->watchers($schedule)->except([$account->id]), $schedule, "{$name} has booked an appointment for {$date}. Assign it to sales to confirm.");

            return;
        }

        $booker = $schedule->booked_by === null ? null : User::query()->find($schedule->booked_by);
        $sales = $this->assignee($schedule);
        $bookerName = $booker->name ?? 'Regray';

        if ($sales !== null) {
            $this->send([$sales], $schedule, "{$bookerName} has assigned you an appointment with {$name} on {$date}. Confirm or decline it.");
        }

        $this->send($this->watchers($schedule)->except(array_filter([$booker?->id, $sales?->id])), $schedule, "{$bookerName} has booked an appointment with {$name} for {$date}.");
    }

    /** A change to the appointment, as it left it: `$arrivalAt` is its time after the change. */
    public function changed(Schedule $schedule, ScheduleChangeKind $kind, CarbonInterface $arrivalAt, ?int $changedBy, ?string $reason = null): void
    {
        if ($schedule->isInquiryVisit()) {
            return;
        }

        $account = $this->clientAccount($schedule);
        $name = $this->clientName($schedule);
        $date = $this->date($arrivalAt);
        $actor = $changedBy === null ? null : User::query()->find($changedBy);
        $staffActor = $actor !== null && $actor->id !== $account?->id ? $actor : null;
        $byStaff = $staffActor->name ?? 'Regray';
        $othersThanActor = $this->watchers($schedule)->except(array_filter([$actor?->id]));

        match ($kind) {
            ScheduleChangeKind::Rescheduled => $this->changedBySomeone($schedule, $account, $name, $staffActor, $date, 'updated'),
            ScheduleChangeKind::Cancelled => $this->changedBySomeone($schedule, $account, $name, $staffActor, $date, 'cancelled'),
            ScheduleChangeKind::Assigned => $this->assigned($schedule, $name, $staffActor, $date),
            ScheduleChangeKind::Confirmed => $this->toClientAndTeam(
                $schedule,
                $account,
                $othersThanActor,
                "Your appointment on {$date} has been confirmed.",
                "{$byStaff} has confirmed the appointment with {$name} on {$date}.",
            ),
            ScheduleChangeKind::Declined => $this->send(
                $othersThanActor,
                $schedule,
                "{$byStaff} has declined the appointment with {$name} on {$date}: ".rtrim($reason ?? $schedule->decline_reason ?? 'no reason given', '.').'. Assign it to someone else.',
            ),
            ScheduleChangeKind::Served => $this->toClientAndTeam(
                $schedule,
                $account,
                $othersThanActor,
                "Your appointment on {$date} has been marked as served.",
                "{$byStaff} has marked the appointment with {$name} on {$date} as served.",
            ),
            ScheduleChangeKind::Expired => $this->toClientAndTeam(
                $schedule,
                $schedule->isKnownToClient() ? $account : null,
                $this->watchers($schedule),
                "Your appointment request for {$date} has expired.",
                "The appointment with {$name} on {$date} has expired.",
            ),
        };
    }

    /**
     * Remind the sales member an appointment is waiting on to confirm or
     * decline it (`schedules:remind`). Only them: marketing already
     * knows it waits.
     */
    public function remindSales(Schedule $schedule): void
    {
        $sales = $this->assignee($schedule);

        if ($sales === null || $schedule->isInquiryVisit()) {
            return;
        }

        $this->send([$sales], $schedule, "Reminder: the appointment with {$this->clientName($schedule)} on {$this->date($schedule->arrival_at)} is still waiting for you. Confirm or decline it.");
    }

    /**
     * Remind the marketers handling an appointment that it still has nobody
     * to confirm it (`schedules:remind`): who booked or last assigned it, and
     * a client's booking's queue (Schedule::marketingQueue()). Not the owner
     * or the admins, who read every one.
     */
    public function remindMarketing(Schedule $schedule): void
    {
        if ($schedule->isInquiryVisit()) {
            return;
        }

        $handlerIds = array_filter([
            $schedule->booked_by_type === BookedByType::Staff ? $schedule->booked_by : null,
            $schedule->assigned_by,
        ]);
        $marketers = User::query()->whereKey($handlerIds)->get()->merge($schedule->marketingQueue())->unique('id');
        $declined = $schedule->declined_at === null ? '' : ' since it was declined';

        $this->send($marketers, $schedule, "Reminder: the appointment with {$this->clientName($schedule)} on {$this->date($schedule->arrival_at)} has had no sales member{$declined}. Assign it to sales to confirm.");
    }

    /** Assigned to a sales member: they are asked to confirm it, and the other marketers handling it told. */
    private function assigned(Schedule $schedule, string $name, ?User $staffActor, string $date): void
    {
        $sales = $this->assignee($schedule);

        if ($sales === null) {
            return;
        }

        $by = $staffActor->name ?? 'Regray';

        $this->send([$sales], $schedule, "{$by} has assigned you an appointment with {$name} on {$date}. Confirm or decline it.");
        $this->send(
            $this->watchers($schedule)->except(array_filter([$staffActor?->id, $sales->id])),
            $schedule,
            "{$by} has assigned the appointment with {$name} on {$date} to {$sales->name}.",
        );
    }

    /**
     * Updated or cancelled (`$change`), by the client or by staff: whoever
     * did it is told it worked, and everyone else what changed. The client is
     * told only of an appointment they know of, and a walk-in never.
     */
    private function changedBySomeone(Schedule $schedule, ?User $account, string $name, ?User $staffActor, string $date, string $change): void
    {
        if ($staffActor === null) {
            if ($account === null) {
                return;
            }

            $this->send([$account], $schedule, "You have successfully {$change} your appointment on {$date}.");
            $this->send($this->watchers($schedule)->except([$account->id]), $schedule, "{$name} has {$change} their appointment on {$date}.");

            return;
        }

        $this->send([$staffActor], $schedule, "You have successfully {$change} the appointment with {$name}.");

        if ($account !== null && $schedule->isKnownToClient()) {
            $this->send([$account], $schedule, "Your appointment on {$date} has been {$change}.");
        }

        $this->send($this->watchers($schedule)->except([$staffActor->id]), $schedule, "{$staffActor->name} has {$change} the appointment with {$name} on {$date}.");
    }

    /** @param  iterable<User>  $team */
    private function toClientAndTeam(Schedule $schedule, ?User $client, iterable $team, string $toClient, string $toTeam): void
    {
        if ($client !== null) {
            $this->send([$client], $schedule, $toClient);
        }

        $this->send($team, $schedule, $toTeam);
    }

    /**
     * Everyone on staff who reads the appointment (Schedule::scopeVisibleTo()),
     * each once: the owner and the admin, the marketers handling it, and the
     * sales member it is assigned to.
     *
     * @return Collection<int, User>
     */
    private function watchers(Schedule $schedule): Collection
    {
        $handlerIds = array_filter([
            $schedule->booked_by_type === BookedByType::Staff ? $schedule->booked_by : null,
            $schedule->assigned_by,
            $schedule->assigned_to,
        ]);

        $people = User::role([Role::Owner->value, Role::Admin->value])->get()
            ->merge(User::query()->whereKey($handlerIds)->get())
            ->merge($schedule->marketingQueue());

        return $people->unique('id')->keyBy('id');
    }

    private function assignee(Schedule $schedule): ?User
    {
        return $schedule->assigned_to === null ? null : User::query()->find($schedule->assigned_to);
    }

    /** The client account the appointment is for, to be told: null for a walk-in. */
    private function clientAccount(Schedule $schedule): ?User
    {
        return $schedule->client_id === null ? null : User::query()->find($schedule->client_id);
    }

    /** Whose appointment it is, for staff: the account's name, else the walk-in's. */
    private function clientName(Schedule $schedule): string
    {
        return $schedule->clientName() ?? 'a walk-in client';
    }

    /** @param  iterable<User>  $recipients */
    private function send(iterable $recipients, Schedule $schedule, string $body): void
    {
        $this->notifications->send($recipients, NotificationType::Schedule, $schedule->id, $body);
    }

    /** "Nov 14, 2026 at 10:00 AM", in the studio's zone. */
    private function date(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone((string) config('schedule.timezone'))->format('M j, Y \a\t g:i A');
    }
}

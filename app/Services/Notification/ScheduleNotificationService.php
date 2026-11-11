<?php

namespace App\Services\Notification;

use App\Enums\BookedByType;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\ScheduleCardStatus;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Appointment notifications, each a short sentence: "you" to whoever it is
 * about, a name to everyone else. The team here is every marketing and sales
 * user, with the owner and the admin.
 *
 * - A client books, updates or cancels: the client is sent a confirmation, and
 *   the team is told.
 * - Staff book for a client: the booker is told it is booked, the client that
 *   it was booked for them, and the team, booker included, that there is a new
 *   appointment.
 * - Staff update or cancel (marking one as not attended is a cancel): the
 *   staff member is told it succeeded, and the client and the team, that staff
 *   member included, what changed.
 * - Approved, served or expired: the client and the team, never whoever did it.
 *
 * Never for an inquiry's visit, which follows its inquiry, nor for one with no
 * client account.
 */
class ScheduleNotificationService
{
    public function __construct(
        public readonly NotificationService $notifications,
    ) {}

    public function booked(Schedule $schedule): void
    {
        $client = $this->client($schedule);

        if ($client === null) {
            return;
        }

        $date = $this->date($schedule->arrival_at);

        if ($schedule->booked_by_type === BookedByType::Client) {
            $this->send([$client], $schedule, "Your appointment request for {$date} has been sent.");
            $this->send($this->team()->reject(fn (User $user): bool => $user->id === $client->id), $schedule, "{$client->name} has booked an appointment for {$date}.");

            return;
        }

        $booker = $schedule->booked_by === null ? null : User::query()->find($schedule->booked_by);

        if ($booker !== null) {
            $this->send([$booker], $schedule, "You have successfully booked an appointment with {$client->name} for {$date}.");
        }

        $this->send([$client], $schedule, "An appointment has been booked for you on {$date}.");
        $this->send($this->team(), $schedule, ($booker->name ?? 'Regray')." has booked an appointment with {$client->name} for {$date}.");
    }

    /** A change to the appointment, as it left it: `$arrivalAt` is its time after the change. */
    public function changed(Schedule $schedule, ScheduleCardStatus $announced, CarbonInterface $arrivalAt, ?int $changedBy): void
    {
        $client = $this->client($schedule);

        if ($client === null) {
            return;
        }

        $date = $this->date($arrivalAt);
        $actor = $changedBy === null ? null : User::query()->find($changedBy);
        $staffActor = $actor !== null && $actor->id !== $client->id ? $actor : null;
        $othersThanActor = $this->team()->reject(fn (User $user): bool => $user->id === $actor?->id);
        $byStaff = $staffActor->name ?? 'Regray';

        match ($announced) {
            ScheduleCardStatus::Rescheduled => $this->changedBySomeone($schedule, $client, $staffActor, $date, 'updated'),
            ScheduleCardStatus::Cancelled => $this->changedBySomeone($schedule, $client, $staffActor, $date, 'cancelled'),
            ScheduleCardStatus::Confirmed => $this->toClientAndTeam(
                $schedule,
                $client,
                $othersThanActor,
                "Your appointment on {$date} has been confirmed.",
                "{$byStaff} has approved the appointment with {$client->name} on {$date}.",
            ),
            ScheduleCardStatus::Served => $this->toClientAndTeam(
                $schedule,
                $client,
                $othersThanActor,
                "Your appointment on {$date} has been marked as served.",
                "{$byStaff} has marked the appointment with {$client->name} on {$date} as served.",
            ),
            ScheduleCardStatus::Expired => $this->toClientAndTeam(
                $schedule,
                $client,
                $this->team(),
                "Your appointment request for {$date} has expired.",
                "The appointment with {$client->name} on {$date} has expired.",
            ),
            ScheduleCardStatus::Pending => null,
        };
    }

    /**
     * Updated or cancelled (`$change`), by the client or by staff: whoever
     * did it is told it worked, and everyone else what changed.
     */
    private function changedBySomeone(Schedule $schedule, User $client, ?User $staffActor, string $date, string $change): void
    {
        if ($staffActor === null) {
            $this->send([$client], $schedule, "You have successfully {$change} your appointment on {$date}.");
            $this->send($this->team()->reject(fn (User $user): bool => $user->id === $client->id), $schedule, "{$client->name} has {$change} their appointment on {$date}.");

            return;
        }

        $this->send([$staffActor], $schedule, "You have successfully {$change} the appointment with {$client->name}.");
        $this->send([$client], $schedule, "Your appointment on {$date} has been {$change}.");
        $this->send($this->team(), $schedule, "{$staffActor->name} has {$change} the appointment with {$client->name} on {$date}.");
    }

    /** @param  iterable<User>  $team */
    private function toClientAndTeam(Schedule $schedule, User $client, iterable $team, string $toClient, string $toTeam): void
    {
        $this->send([$client], $schedule, $toClient);
        $this->send($team, $schedule, $toTeam);
    }

    /** The client account the appointment is for: null for an inquiry's visit or a walk-in's. */
    private function client(Schedule $schedule): ?User
    {
        if ($schedule->isInquiryVisit() || $schedule->client_id === null) {
            return null;
        }

        return User::query()->find($schedule->client_id);
    }

    /** @return Collection<int, User> */
    private function team(): Collection
    {
        return User::role([Role::Marketing->value, Role::Sales->value, Role::Owner->value, Role::Admin->value])->get();
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

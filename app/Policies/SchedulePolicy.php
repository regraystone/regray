<?php

namespace App\Policies;

use App\Enums\ScheduleAction;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may change an appointment.
 *
 * Each ability asks for the action's permission (ScheduleAction::permission()),
 * then: a client may only update or cancel an appointment that is theirs;
 * staff holding the permission may act on an appointment they read
 * (Schedule::isVisibleTo()), and confirming or declining is for the sales
 * member it is assigned to alone. Among staff, marketing updates, cancels and
 * assigns; sales confirms and declines, and alone marks how it went.
 *
 * Whether the appointment's state still allows the change is not decided here
 * but by ScheduleService::refusalFor(), because the super-admin passes every
 * policy through Gate::before and must still be held to it.
 */
class SchedulePolicy
{
    public function update(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Update);
    }

    public function cancel(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Cancel);
    }

    public function assign(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Assign);
    }

    public function confirm(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Confirm);
    }

    public function decline(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Decline);
    }

    public function serve(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::Serve);
    }

    public function noShow(User $user, Schedule $schedule): Response
    {
        return $this->allows($user, $schedule, ScheduleAction::NoShow);
    }

    private function allows(User $user, Schedule $schedule, ScheduleAction $action): Response
    {
        if (! $user->can($action->permission()->value)) {
            return Response::deny();
        }

        if ($user->isClient()) {
            // Another client's visit answers as missing, like one that does not exist,
            // so a client cannot tell which visit ids are taken.
            if ($schedule->client_id !== $user->id) {
                return Response::denyAsNotFound();
            }

            return $action->isOpenToClients() ? Response::allow() : Response::deny();
        }

        if (! $schedule->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        if ($action->isForAssignedSales() && $schedule->assigned_to !== $user->id) {
            return Response::deny('Only the sales member this appointment is assigned to can answer it.');
        }

        return Response::allow();
    }
}

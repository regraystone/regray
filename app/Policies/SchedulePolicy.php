<?php

namespace App\Policies;

use App\Enums\ScheduleAction;
use App\Models\Schedule;
use App\Models\User;

/**
 * Who may change an appointment.
 *
 * Each ability asks for the action's permission (ScheduleAction::permission()),
 * then: a client may only update or cancel an appointment that is theirs, and
 * never approve it or mark how it went; staff holding the permission (among
 * staff, marketing and sales) may act on any appointment.
 *
 * Whether the appointment's state still allows the change is not decided here
 * but by ScheduleService::refusalFor(), because the super-admin passes every
 * policy through Gate::before and must still be held to it.
 */
class SchedulePolicy
{
    public function update(User $user, Schedule $schedule): bool
    {
        return $this->allows($user, $schedule, ScheduleAction::Update);
    }

    public function cancel(User $user, Schedule $schedule): bool
    {
        return $this->allows($user, $schedule, ScheduleAction::Cancel);
    }

    public function approve(User $user, Schedule $schedule): bool
    {
        return $this->allows($user, $schedule, ScheduleAction::Approve);
    }

    public function serve(User $user, Schedule $schedule): bool
    {
        return $this->allows($user, $schedule, ScheduleAction::Serve);
    }

    public function noShow(User $user, Schedule $schedule): bool
    {
        return $this->allows($user, $schedule, ScheduleAction::NoShow);
    }

    private function allows(User $user, Schedule $schedule, ScheduleAction $action): bool
    {
        if (! $user->can($action->permission()->value)) {
            return false;
        }

        if ($user->isClient()) {
            return $action->isOpenToClients() && $schedule->client_id === $user->id;
        }

        return true;
    }
}

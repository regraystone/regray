<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class ConfirmScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** The sales member an appointment is assigned to confirms it. */
    public function execute(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->confirm($schedule, $by, $ipAddress, $userAgent);
    }
}

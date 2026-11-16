<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class AssignScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** Assign a pending appointment to a sales member, who confirms or declines it. */
    public function execute(Schedule $schedule, User $by, User $sales, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->assign($schedule, $by, $sales, $ipAddress, $userAgent);
    }
}

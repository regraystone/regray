<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class ServeScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** Mark a confirmed appointment as attended: it is served. */
    public function execute(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->serve($schedule, $by, $ipAddress, $userAgent);
    }
}

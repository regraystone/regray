<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class NoShowScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** Mark a confirmed appointment as not attended: it is cancelled with "Did not attend". */
    public function execute(Schedule $schedule, User $by, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->noShow($schedule, $by, $ipAddress, $userAgent);
    }
}

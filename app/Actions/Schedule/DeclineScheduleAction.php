<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class DeclineScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** The sales member an appointment is assigned to declines it, saying why, back to marketing. */
    public function execute(Schedule $schedule, User $by, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->decline($schedule, $by, $reason, $ipAddress, $userAgent);
    }
}

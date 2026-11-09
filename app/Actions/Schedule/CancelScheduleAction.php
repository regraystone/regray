<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;

class CancelScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** Cancel an appointment, saying why. */
    public function execute(Schedule $schedule, User $by, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->cancel($schedule, $by, $reason, $ipAddress, $userAgent);
    }
}

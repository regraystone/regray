<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;
use Carbon\CarbonImmutable;

class UpdateScheduleAction
{
    public function __construct(
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {}

    /** Update an appointment, saying why. */
    public function execute(Schedule $schedule, User $by, string $purpose, CarbonImmutable $arrivalAt, ?string $notes, string $reason, ?string $ipAddress, ?string $userAgent): Schedule
    {
        return $this->scheduleChangeService->update($schedule, $by, $purpose, $arrivalAt, $notes, $reason, $ipAddress, $userAgent);
    }
}

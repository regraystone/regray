<?php

namespace App\Actions\Schedule;

use App\Events\ScheduleBooked;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;

class BookScheduleAction
{
    public function __construct(
        public readonly ScheduleService $scheduleService,
    ) {}

    /** Save a client's booking and let the team hear about it. */
    public function execute(User $client, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $schedule = $this->scheduleService->createForBooking($client, $purpose, $arrivalAt, $notes);

        event(new ScheduleBooked($schedule));

        return $schedule;
    }
}

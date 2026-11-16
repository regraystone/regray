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

    /** Save a client's booking, pending, and let marketing hear about it. */
    public function execute(User $client, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $schedule = $this->scheduleService->createForBooking($client, $purpose, $arrivalAt, $notes);

        event(new ScheduleBooked($schedule));

        return $schedule;
    }

    /**
     * Save a booking marketing made for a client, assigned at once to
     * `$sales`, who is told to confirm it. The client is told once it is
     * confirmed.
     */
    public function executeForStaff(User $staff, User $client, User $sales, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $schedule = $this->scheduleService->createForStaffBooking($staff, $client, $sales, $purpose, $arrivalAt, $notes);

        event(new ScheduleBooked($schedule));

        return $schedule;
    }
}

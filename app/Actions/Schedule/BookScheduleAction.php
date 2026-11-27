<?php

namespace App\Actions\Schedule;

use App\Enums\ScheduleKind;
use App\Events\ScheduleBooked;
use App\Models\Client;
use App\Models\Inquiry;
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
     * Save a booking marketing made for a client (an account, or a walk-in's
     * client record), assigned at once to `$sales`, who is told to confirm it.
     * A client with an account is told once it is confirmed; a walk-in never.
     */
    /**
     * A client's installation or delivery for one of their accepted orders,
     * awaiting the order's sales member (ScheduleService::createForOrder()).
     */
    public function executeForOrder(User $client, Inquiry $order, ScheduleKind $kind, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $schedule = $this->scheduleService->createForOrder($client, $order, $kind, $arrivalAt, $notes);

        event(new ScheduleBooked($schedule));

        return $schedule;
    }

    public function executeForStaff(User $staff, User|Client $client, User $sales, string $purpose, CarbonImmutable $arrivalAt, ?string $notes): Schedule
    {
        $schedule = $this->scheduleService->createForStaffBooking($staff, $client, $sales, $purpose, $arrivalAt, $notes);

        event(new ScheduleBooked($schedule));

        return $schedule;
    }
}

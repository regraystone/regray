<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A client booked an appointment. Dispatched by BookScheduleAction once the
 * schedule is saved.
 */
class ScheduleBooked implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Schedule $schedule,
    ) {}
}

<?php

namespace App\Events;

use App\Enums\ScheduleChangeKind;
use App\Models\Schedule;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An appointment was updated, cancelled, assigned to sales, confirmed or
 * declined by sales, served, marked as not attended or expired. Dispatched by
 * ScheduleChangeService once the change is committed; PostScheduleCard
 * announces it in the client's Regray thread when the client sees that step
 * (ScheduleChangeKind::card()).
 *
 * `$revision`, `$arrivalAt` and `$reason` are the appointment as this change
 * left it, so the card keeps them even if a later change lands before the
 * queued listener runs. `$changedBy` is who made it: null when nobody did (it
 * expired). SendScheduleNotifications tells everyone concerned.
 */
class ScheduleChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Schedule $schedule,
        public ScheduleChangeKind $kind,
        public int $revision,
        public string $arrivalAt,
        public ?string $reason,
        public ?int $changedBy = null,
    ) {}
}

<?php

namespace App\Actions\Schedule;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

class ListSchedulesAction
{
    /** How many visits the Upcoming panel lists. */
    public const UPCOMING_LIMIT = 3;

    public function __construct(
        public readonly ScheduleService $scheduleService,
    ) {}

    /**
     * Every visit the viewer may see arriving in `[$from, $to)`, any status,
     * for the week grid and the mini calendar's dots.
     *
     * @return Collection<int, Schedule>
     */
    public function inRange(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->scheduleService->between($viewer, $from, $to);
    }

    /**
     * The next UPCOMING_LIMIT visits, soonest first, for the Upcoming panel.
     *
     * Pending and confirmed visits only; completed and cancelled ones never
     * show. A visit still counts for `schedule.ongoing_minutes` after its
     * arrival, so one happening right now stays at the top of the list.
     *
     * @return Collection<int, Schedule>
     */
    public function upcoming(User $viewer): Collection
    {
        $since = Date::now()->subMinutes((int) config('schedule.ongoing_minutes'));

        return $this->scheduleService->nextOpenSince($viewer, $since, self::UPCOMING_LIMIT);
    }
}

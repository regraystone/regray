<?php

namespace App\Services;

use App\Enums\BookedByType;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads and writes schedules.
 *
 * Who sees what is decided here from the signed-in user, never from the
 * request: a client sees only their own visits, every staff role sees all.
 */
class ScheduleService
{
    public const INQUIRY_CANCELLED_REASON = 'Inquiry cancelled';

    /** When a project with a date but no time of its own is booked for: the showroom opens at ten. */
    public const DEFAULT_ARRIVAL_TIME = '10:00';

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
    ) {}

    /**
     * Every visit the viewer may see arriving in `[$from, $to)`, any status.
     *
     * @return Collection<int, Schedule>
     */
    public function between(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->scheduleRepository->between($this->clientScope($viewer), $from, $to);
    }

    /**
     * The viewer's next `$limit` pending or confirmed visits arriving on or
     * after `$since`, soonest first.
     *
     * @return Collection<int, Schedule>
     */
    public function nextOpenSince(User $viewer, CarbonInterface $since, int $limit): Collection
    {
        return $this->scheduleRepository->nextOpenSince($this->clientScope($viewer), $since, $limit);
    }

    /**
     * The visit an inquiry project is booked as.
     *
     * The client made it by sending the inquiry, so it is theirs and booked by
     * them. It starts as pending until Marketing or Sales approve it. The date
     * and time are what the client typed, read in the studio's zone. Only a
     * visit asks for a time; any other project is booked for
     * DEFAULT_ARRIVAL_TIME on its date.
     *
     * The notes name the inquiry and the project, since one inquiry can hold
     * several projects and so book several visits.
     */
    public function createForInquiryProject(Inquiry $inquiry, User $client, string $projectName, string $location, string $date, ?string $time): Schedule
    {
        return $this->scheduleRepository->create([
            'client_id' => $client->id,
            'booked_by' => $client->id,
            'booked_by_type' => BookedByType::Client,
            'inquiry_id' => $inquiry->id,
            'title' => $projectName,
            'arrival_at' => self::arrivalAt($date, $time ?? self::DEFAULT_ARRIVAL_TIME)->setTimezone(config('app.timezone')),
            'status' => ScheduleStatus::Pending,
            'notes' => "Inquiry {$inquiry->reference} · {$projectName} · {$location}",
        ]);
    }

    /** Cancel the inquiry's open visits along with it. */
    public function cancelForInquiry(Inquiry $inquiry, CarbonInterface $cancelledAt): void
    {
        $this->scheduleRepository->cancelOpenForInquiry($inquiry, $cancelledAt, self::INQUIRY_CANCELLED_REASON);
    }

    /**
     * A `Y-m-d` date and an `H:i` time, read in the studio's zone.
     *
     * Convert it to the app's zone before writing it or binding it in a query:
     * Eloquent formats a date without its offset.
     */
    public static function arrivalAt(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::timezone());
    }

    public static function timezone(): string
    {
        return config('schedule.timezone');
    }

    /** The client whose visits the viewer is limited to, or null for staff. */
    private function clientScope(User $viewer): ?User
    {
        return $viewer->isClient() ? $viewer : null;
    }
}

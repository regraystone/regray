<?php

namespace App\Repositories\Contracts;

use App\Enums\BookedByType;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleRepositoryInterface
{
    /**
     * @param  array{client_id: ?int, booked_by: ?int, booked_by_type: BookedByType, inquiry_id: ?int, title: string, arrival_at: CarbonInterface, status: ScheduleStatus, notes: ?string, approved_by?: ?int, approved_at?: ?CarbonInterface}  $attributes
     */
    public function create(array $attributes): Schedule;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Schedule $schedule, array $attributes): void;

    /**
     * Every schedule arriving in `[$from, $to)`, whatever its status, ordered by
     * arrival, with its inquiry loaded. Only `$client`'s when a client is given,
     * everyone's when it is null.
     *
     * @return Collection<int, Schedule>
     */
    public function between(?User $client, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * The first `$limit` pending or confirmed schedules arriving on or after
     * `$since`, soonest first, with their inquiry loaded. Scoped like
     * between().
     *
     * @return Collection<int, Schedule>
     */
    public function nextOpenSince(?User $client, CarbonInterface $since, int $limit): Collection;

    /**
     * Cancel the inquiry's pending and confirmed schedules. Served, expired and
     * already cancelled ones are left as they are.
     *
     * @return int How many were cancelled.
     */
    public function cancelOpenForInquiry(Inquiry $inquiry, CarbonInterface $cancelledAt, string $reason): int;

    /**
     * The schedule, its row locked until the transaction ends, so two changes to
     * one appointment are made one after the other.
     */
    public function findForUpdate(int $id): Schedule;

    /**
     * The ids of the appointments still pending at `$now`, their time passed.
     * Never an inquiry's visit.
     *
     * @return list<int>
     */
    public function pendingPastIds(CarbonInterface $now): array;
}

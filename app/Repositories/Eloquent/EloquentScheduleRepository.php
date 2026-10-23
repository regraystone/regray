<?php

namespace App\Repositories\Eloquent;

use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
    private const OPEN_STATUSES = [ScheduleStatus::Pending, ScheduleStatus::Confirmed];

    /** `client_id` is not fillable, so the row is filled by hand. */
    public function create(array $attributes): Schedule
    {
        $schedule = new Schedule;
        $schedule->forceFill($attributes)->save();

        return $schedule;
    }

    public function update(Schedule $schedule, array $attributes): void
    {
        $schedule->forceFill($attributes)->save();
    }

    public function between(?User $client, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->visibleTo($client)
            ->with('inquiry:id,reference')
            ->where('arrival_at', '>=', $from)
            ->where('arrival_at', '<', $to)
            ->orderBy('arrival_at')
            ->orderBy('id')
            ->get();
    }

    public function nextOpenSince(?User $client, CarbonInterface $since, int $limit): Collection
    {
        return $this->visibleTo($client)
            ->with('inquiry:id,reference')
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('arrival_at', '>=', $since)
            ->orderBy('arrival_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function cancelOpenForInquiry(Inquiry $inquiry, CarbonInterface $cancelledAt, string $reason): int
    {
        return Schedule::query()
            ->where('inquiry_id', $inquiry->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->update([
                'status' => ScheduleStatus::Cancelled,
                'cancelled_at' => $cancelledAt,
                'cancel_reason' => $reason,
                'updated_at' => $cancelledAt,
            ]);
    }

    /**
     * Only the client's own schedules when a client is given, every schedule
     * when it is null. The caller decides which from the signed-in user, never
     * from the request.
     *
     * @return Builder<Schedule>
     */
    private function visibleTo(?User $client): Builder
    {
        return Schedule::query()
            ->when($client !== null, fn (Builder $query): Builder => $query->where('client_id', $client?->id));
    }
}

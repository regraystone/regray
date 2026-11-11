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

    public function between(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->visibleTo($viewer)
            ->with(['inquiry:id,reference', 'client:id,name', 'bookedBy:id,name'])
            ->where('arrival_at', '>=', $from)
            ->where('arrival_at', '<', $to)
            ->orderBy('arrival_at')
            ->orderBy('id')
            ->get();
    }

    public function nextOpenSince(User $viewer, CarbonInterface $since, int $limit): Collection
    {
        return $this->visibleTo($viewer)
            ->with(['inquiry:id,reference', 'client:id,name', 'bookedBy:id,name'])
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

    public function findForUpdate(int $id, ?User $viewer = null): Schedule
    {
        return Schedule::query()
            ->when($viewer !== null && ! $viewer->isClient(), fn (Builder $query) => $query->visibleTo($viewer))
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function pendingPastIds(CarbonInterface $now): array
    {
        /** @var list<int> */
        return Schedule::query()
            ->whereNull('inquiry_id')
            ->where('status', ScheduleStatus::Pending)
            ->where('arrival_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * A client's own schedules; for staff, those they read
     * (Schedule::scopeVisibleTo()). Decided from the signed-in user the caller
     * passes, never from the request.
     *
     * @return Builder<Schedule>
     */
    private function visibleTo(User $viewer): Builder
    {
        return $viewer->isClient()
            ? Schedule::query()->where('client_id', $viewer->id)
            : Schedule::query()->visibleTo($viewer);
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Data\OverviewRange;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Overview's figures (OverviewRepository). A `null` person is the whole
 * studio, for the owner's and the admin's totals.
 *
 * A "to do" list comes back as `[count, first rows]`: how many there are, and
 * the oldest few to show.
 */
interface OverviewRepositoryInterface
{
    /** @return array{won: int, value: string, unbilled: string, lost: int, quotations: int, averageDaysToClose: float|null} */
    public function salesResults(?User $rep, OverviewRange $range): array;

    /** @return array<string, array{count: int, total: string}> By undecided sales status. */
    public function salesPipeline(?User $rep): array;

    /** @return array<string, array<string, array{count: int, total: string}>> By bucket start, then `won` or `rejected`. */
    public function salesTrend(?User $rep, OverviewRange $range): array;

    /** @return array{0: int, 1: Collection<int, Schedule>} */
    public function visitsAwaitingConfirmation(User $rep, int $limit): array;

    /** @return array{0: int, 1: Collection<int, Schedule>} */
    public function visitsToMarkAttended(User $rep, CarbonImmutable $now, int $limit): array;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function staleQuotes(User $rep, CarbonImmutable $now, int $limit): array;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function wonWithoutJobOrder(User $rep, int $limit): array;

    public function visitsAttended(User $rep, OverviewRange $range): int;

    /** @return array{newLeads: int, received: int, opened: int|null, won: int, lost: int, averageHoursToDecide: float|null, sold: int, soldValue: string} */
    public function marketingResults(?User $marketer, OverviewRange $range): array;

    /** @return array<string, array<string, int>> By bucket start, then `newLeads`, `won` or `lost`. */
    public function marketingTrend(User $marketer, OverviewRange $range): array;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function idleInquiries(User $marketer, int $limit): array;

    /** @return array{0: int, 1: int, 2: Collection<int, Schedule>} All waiting, how many of them sales declined, the first rows. */
    public function bookingsToAssign(?User $marketer, int $limit): array;

    public function unclaimedSupportChats(): int;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function awaitingPayment(User $user, int $limit): array;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function forRefund(?User $user, int $limit): array;

    /** @return array<string, int> Open jobs by process status. */
    public function processCounts(User $user): array;

    /** @return array{overdue: int, dueSoon: int} */
    public function dueCounts(?User $user, CarbonImmutable $now, string $timezone): array;

    /**
     * @param  list<ProcessStatus>  $statuses
     * @return array{0: int, 1: Collection<int, Inquiry>}
     */
    public function jobsAt(User $user, array $statuses, int $limit): array;

    /** @return array{0: int, 1: Collection<int, Inquiry>} */
    public function overdueJobs(User $user, CarbonImmutable $now, string $timezone, int $limit): array;

    /** @return Collection<int, Inquiry> */
    public function deadlinesAhead(User $user, CarbonImmutable $now, string $timezone, int $limit): Collection;

    public function completedJobs(User $user, OverviewRange $range): int;

    /** @return array{0: int, 1: int, 2: Collection<int, Inquiry>} Open, due within the window, the first rows. */
    public function openAssigned(User $user, CarbonImmutable $now, string $timezone, int $limit): array;

    /** @return Collection<int, Schedule> */
    public function upcomingVisits(User $user, CarbonImmutable $now, int $days, int $limit): Collection;

    /** @return array<string, int> Every inquiry by display status. */
    public function pipelineByStatus(): array;

    public function staleInquiries(CarbonImmutable $now): int;

    public function newClients(OverviewRange $range): int;

    /** @return Collection<int, User> */
    public function staff(Role $role): Collection;
}

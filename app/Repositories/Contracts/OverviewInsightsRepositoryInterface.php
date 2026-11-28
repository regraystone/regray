<?php

namespace App\Repositories\Contracts;

use App\Data\OverviewRange;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The Overview's deeper figures (OverviewInsightsRepository). `$as` says whose
 * inquiries: `rep` (assigned to a sales rep), `marketer` (on the leads a
 * marketing user holds) or `reader` (the ones they may see); a null user is
 * the whole studio.
 */
interface OverviewInsightsRepositoryInterface
{
    /**
     * Of the inquiries received within the dates, how many have got this far
     * by now.
     *
     * @param  'rep'|'marketer'|'reader'  $as
     * @return array{received: int, passed: int, quoted: int, won: int, paid: int, completed: int}
     */
    public function funnel(string $as, ?User $user, OverviewRange $range): array;

    /** @return array{completed: int, onTime: int, averageDaysLate: float|null} Jobs completed within the dates, against their deadline. */
    public function onTime(?User $reader, OverviewRange $range): array;

    /** @param  'rep'|'marketer'|'reader'  $as */
    public function daysToFirstQuote(string $as, ?User $user, OverviewRange $range): ?float;

    public function daysWonToCompleted(?User $reader, OverviewRange $range): ?float;

    public function daysWonToPaid(?User $reader, OverviewRange $range): ?float;

    /** @return list<array{service: string, won: int, value: string, lost: int}> */
    public function byService(?User $rep, OverviewRange $range): array;

    /** @return array{online: int, staff: int, walkIn: int} */
    public function channels(?User $marketer, OverviewRange $range): array;

    /** @return list<array{label: string, count: int}> */
    public function places(?User $marketer, OverviewRange $range, int $limit): array;

    /** @return array{won: int, averageQuotes: float|null, revised: int, contractVsQuote: float|null} */
    public function quotes(?User $rep, OverviewRange $range): array;

    /** @return array{attended: int, expired: int, cancelled: int, unmarked: int, ledToWin: int} */
    public function visitOutcomes(?User $reader, OverviewRange $range, CarbonImmutable $now): array;

    /** @return list<array{bucket: string, count: int, total: string}> By days since won. */
    public function ageing(User $reader, CarbonImmutable $now): array;

    /** @return list<array{label: string, detail: string|null, inquiries: int, won: int}> */
    public function topStones(?User $marketer, OverviewRange $range, int $limit): array;

    /** @return list<array{label: string, detail: string|null, inquiries: int, won: int}> */
    public function topBrands(?User $marketer, OverviewRange $range, int $limit): array;
}

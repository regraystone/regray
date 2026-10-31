<?php

namespace App\Repositories\Contracts;

use App\Data\StoneFilterData;
use App\Models\Stone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    /** @return LengthAwarePaginator<int, Stone> */
    public function paginate(StoneFilterData $filters, int $perPage): LengthAwarePaginator;

    /** Id and slug only, for saving a stone without the heavy profile query. */
    public function findActiveSummaryBySlug(string $slug): ?Stone;

    /**
     * The ids of the active stones ranked in the top `$count` by how many
     * completed inquiries they are in, ties at the last place included.
     *
     * @return list<int>
     */
    public function bestSellerIds(int $count): array;

    /**
     * @return array{categories: SupportCollection<int, array{value: string, label: string}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array;
}

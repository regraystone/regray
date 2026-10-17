<?php

namespace App\Repositories\Contracts;

use App\Data\StoneFilterData;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    /** @return LengthAwarePaginator<int, Stone> */
    public function paginate(StoneFilterData $filters, int $perPage): LengthAwarePaginator;

    /** Active, recommended stones, ordered by sort_order then name. */
    /**
     * @return Collection<int, Stone>
     */
    public function recommended(): Collection;

    /** Id and slug only, for saving a stone without the heavy profile query. */
    public function findActiveSummaryBySlug(string $slug): ?Stone;

    /**
     * @return array{categories: SupportCollection<int, array{value: string, label: string}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array;
}

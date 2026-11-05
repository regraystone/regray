<?php

namespace App\Repositories\Contracts;

use App\Models\Stone;
use Illuminate\Support\Collection as SupportCollection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    /** Id and slug only, for saving a stone without the heavy profile query. */
    public function findActiveSummaryBySlug(string $slug): ?Stone;

    /**
     * The ids of the active stone categories ranked in the top `$count` by how
     * many completed inquiries their active stones are in, ties at the last
     * place included.
     *
     * @return list<int>
     */
    public function bestSellerCategoryIds(int $count): array;

    /**
     * @return array{categories: SupportCollection<int, array{value: string, label: string, bestSeller: bool}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array;
}

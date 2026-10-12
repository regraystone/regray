<?php

namespace App\Repositories\Contracts;

use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    public function findActiveInPublishedProject(string $stoneSlug, string $projectSlug): ?Stone;

    /** Active, recommended stones, ordered by sort_order then name. */
    /**
     * @return Collection<int, Stone>
     */
    public function recommended(): Collection;

    /** Id and slug only, for saving a stone without the heavy profile query. */
    public function findActiveSummaryBySlug(string $slug): ?Stone;

    /**
     * @return SupportCollection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): SupportCollection;
}

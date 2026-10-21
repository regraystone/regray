<?php

namespace App\Repositories\Contracts;

use App\Data\ProjectFilterData;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    public function findPublishedInStone(string $stoneSlug, string $projectSlug): ?Project;

    public function findPublishedBySlug(string $slug): ?Project;

    /** @return LengthAwarePaginator<int, Project> */
    public function paginatePublished(ProjectFilterData $filters, int $perPage): LengthAwarePaginator;

    /** @return array{categories: Collection<int, array{value: string, label: string}>} */
    public function filterOptions(): array;
}

<?php

namespace App\Repositories\Contracts;

use App\Data\ProjectFilterData;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function findPublishedBySlug(string $slug): ?Project;

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, Project>
     */
    public function paginate(ProjectFilterData $filters, int $perPage): LengthAwarePaginator;
}

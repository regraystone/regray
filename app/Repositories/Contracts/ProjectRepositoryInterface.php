<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    /**
     * The most recently completed published projects, newest first.
     *
     * @return Collection<int, Project>
     */
    public function latestPublished(int $limit): Collection;

    public function findPublishedInStone(string $stoneSlug, string $projectSlug): ?Project;

    public function findPublishedBySlug(string $slug): ?Project;
}

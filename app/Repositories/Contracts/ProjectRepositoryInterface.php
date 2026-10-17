<?php

namespace App\Repositories\Contracts;

use App\Models\Project;

interface ProjectRepositoryInterface
{
    public function findPublishedInStone(string $stoneSlug, string $projectSlug): ?Project;
}

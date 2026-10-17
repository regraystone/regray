<?php

namespace App\Services;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectService
{
    public function __construct(
        public readonly ProjectRepositoryInterface $projectRepository,
    ) {}

    /** Keep a missing published project a domain lookup failure; the HTTP handler renders its 404. */
    public function getPublishedProject(string $stoneSlug, string $projectSlug): Project
    {
        $project = $this->projectRepository->findPublishedInStone($stoneSlug, $projectSlug);

        if ($project === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$projectSlug]);
        }

        return $project;
    }
}

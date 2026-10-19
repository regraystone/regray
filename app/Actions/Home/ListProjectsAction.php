<?php

namespace App\Actions\Home;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListProjectsAction
{
    public function __construct(
        public readonly ProjectService $projectService,
    ) {}

    /**
     * @return array{projects: LengthAwarePaginator<int, Project>, filters: array{search: string|null, projectCategory: string|null}, filterOptions: array{categories: Collection<int, array{value: string, label: string}>}}
     */
    public function execute(ProjectFilterData $filters): array
    {
        return $this->projectService->listPublishedProjects($filters);
    }
}

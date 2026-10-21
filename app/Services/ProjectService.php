<?php

namespace App\Services;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectService
{
    public const PER_PAGE = 12;

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

    public function findPublished(string $slug): Project
    {
        $project = $this->projectRepository->findPublishedBySlug($slug);

        if ($project === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$slug]);
        }

        return $project;
    }

    /**
     * @return array{projects: LengthAwarePaginator<int, Project>, filters: array{search: string|null, projectCategory: string|null}, filterOptions: array{categories: Collection<int, array{value: string, label: string}>}}
     */
    public function listPublishedProjects(ProjectFilterData $filters): array
    {
        $cleaned = $this->cleanFilters($filters);

        return [
            'projects' => $this->projectRepository->paginatePublished($cleaned, self::PER_PAGE),
            'filters' => [
                'search' => $cleaned->search,
                'projectCategory' => $cleaned->projectCategory,
            ],
            'filterOptions' => $this->projectRepository->filterOptions(),
        ];
    }

    public function cleanFilters(ProjectFilterData $filters): ProjectFilterData
    {
        $search = $filters->search === null ? null : trim($filters->search);
        if ($search === '') {
            $search = null;
        } elseif ($search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
        }

        return new ProjectFilterData($search, $filters->projectCategory, $filters->page);
    }
}

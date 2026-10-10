<?php

namespace App\Services;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectService
{
    public const PER_PAGE = 12;

    public const PER_PAGE_MAX = 12;

    public function __construct(
        public readonly ProjectRepositoryInterface $projectRepository,
        public readonly StoneRepositoryInterface $stoneRepository,
        public readonly ProjectCategoryRepositoryInterface $projectCategoryRepository,
    ) {}

    /**
     * @return array{projects: \Illuminate\Pagination\LengthAwarePaginator<int, Project>, filters: array{search: string|null, stone: string|null, category: string|null}, filterOptions: array{stones: Collection<int, array{value: string, label: string}>, categories: Collection<int, array{value: string, label: string}>}}
     */
    public function listProjects(ProjectFilterData $filters): array
    {
        $cleaned = $this->cleanFilters($filters);

        $paginator = $this->projectRepository->paginate($cleaned, self::PER_PAGE);

        $stones = $this->stoneRepository->activeFilterOptions();
        $categories = $this->projectCategoryRepository->activeFilterOptions();

        return [
            'projects' => $paginator,
            'filters' => [
                'search' => $cleaned->search,
                'stone' => $cleaned->stone,
                'category' => $cleaned->category,
            ],
            'filterOptions' => [
                'stones' => $stones,
                'categories' => $categories,
            ],
        ];
    }

    public function cleanFilters(ProjectFilterData $filters): ProjectFilterData
    {
        $search = $filters->search;

        if ($search !== null) {
            $search = trim($search);

            if ($search === '') {
                $search = null;
            } else {
                $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            }
        }

        return new ProjectFilterData(
            search: $search,
            stone: $filters->stone,
            category: $filters->category,
            page: $filters->page,
        );
    }
}

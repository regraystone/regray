<?php

namespace App\Repositories\Eloquent;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    /**
     * Load the profile only after permission has been settled, rather than in route binding.
     * Related keys are retained so Eloquent can match the constrained eager loads.
     */
    public function findPublishedInStone(string $stoneSlug, string $projectSlug): ?Project
    {
        $project = Project::query()
            ->published()
            ->where('slug', $projectSlug)
            ->whereHas('stones', fn (Builder $stones) => $stones->active()->where('slug', $stoneSlug))
            ->with([
                'images' => fn (Relation $images) => $images->getQuery()
                    ->select(['id', 'project_id', 'path'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_category_id', 'slug', 'name', 'thumbnail_path', 'recommended'])
                    ->with('category:id,slug,name')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()
                    ->select(['project_categories.id', 'slug', 'name'])
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->first();

        if ($project !== null) {
            $project->setRelation('parentStone', $project->stones->firstWhere('slug', $stoneSlug));
        }

        return $project;
    }

    public function findPublishedBySlug(string $slug): ?Project
    {
        return Project::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'images' => fn (Relation $images) => $images->getQuery()
                    ->select(['id', 'project_id', 'path'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_category_id', 'slug', 'name', 'thumbnail_path', 'recommended'])
                    ->with('category:id,slug,name')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()
                    ->select(['project_categories.id', 'slug', 'name'])
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginatePublished(ProjectFilterData $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()
            ->published()
            ->select(['id', 'slug', 'name', 'completed_year', 'thumbnail_path']);

        if ($filters->search !== null) {
            $query->where('name', 'ilike', "%{$filters->search}%");
        }

        if ($filters->projectCategory !== null) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $filters->projectCategory));
        }

        return $query
            ->orderByDesc('completed_year')
            ->orderBy('name')
            ->paginate($perPage, pageName: 'page', page: $filters->page);
    }

    /**
     * @return array{categories: Collection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        return [
            'categories' => $this->getCategoryOptions(),
        ];
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function getCategoryOptions(): Collection
    {
        return ProjectCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (ProjectCategory $cat) => [
                'value' => $cat->slug,
                'label' => $cat->name,
            ]);
    }
}

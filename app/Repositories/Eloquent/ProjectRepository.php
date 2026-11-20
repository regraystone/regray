<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function latestPublished(int $limit): Collection
    {
        return Project::query()
            ->published()
            ->orderByDesc('completed_year')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'slug', 'name', 'location', 'completed_year', 'thumbnail_path']);
    }

    /**
     * Load the profile only after permission has been settled, rather than in route binding.
     * Related keys are retained so Eloquent can match the constrained eager loads.
     */
    public function findPublishedInStone(string $stoneSlug, string $projectSlug, bool $withArchived = false): ?Project
    {
        $project = Project::query()
            ->when($withArchived, fn (Builder $projects) => $projects->withTrashed())
            ->published()
            ->where('slug', $projectSlug)
            ->whereHas('stones', fn (Builder $stones) => $stones->active()->where('slug', $stoneSlug))
            ->with([
                'images' => fn (Relation $images) => $images->getQuery()
                    ->select(['id', 'project_id', 'path', 'original_name'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_type_id', 'slug', 'name', 'thumbnail_path'])
                    ->with('type:id,slug,name')
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
                    ->select(['id', 'project_id', 'path', 'original_name'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_type_id', 'slug', 'name', 'thumbnail_path'])
                    ->with('type:id,slug,name')
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

    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator
    {
        return Project::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->with([
                'brand:id,name',
                'stones' => fn (Relation $stones) => $stones->getQuery()->select(['stones.id', 'stones.name'])->orderBy('stones.name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()->select(['project_categories.id', 'project_categories.name'])->orderBy('project_categories.sort_order'),
                'gallery',
            ])
            ->orderByDesc('completed_year')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, page: $page);
    }

    public function adminOptions(): array
    {
        return [
            'brands' => array_values(Brand::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Brand $brand): array => ['id' => $brand->id, 'name' => $brand->name])->all()),
            'stones' => array_values(Stone::query()->active()->with('brand:id,name')->orderBy('name')->orderBy('id')->get(['id', 'brand_id', 'name'])
                ->map(fn (Stone $stone): array => ['id' => $stone->id, 'name' => $stone->name, 'brand' => $stone->brand->name])->all()),
            'categories' => array_values(ProjectCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (ProjectCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all()),
        ];
    }
}

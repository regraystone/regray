<?php

namespace App\Repositories\Eloquent;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    /**
     * Load the profile only after permission has been settled, rather than in route binding.
     * Related keys are retained so Eloquent can match the constrained eager loads.
     */
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
                    ->select(['stones.id', 'slug', 'name', 'thumbnail_path', 'recommended'])
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

    public function paginate(ProjectFilterData $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()
            ->published()
            ->select(['id', 'slug', 'name', 'thumbnail_path']);

        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhereHas('stones', function ($sq) use ($filters): void {
                        $sq->where('name', 'ILIKE', "%{$filters->search}%");
                    })
                    ->orWhereHas('categories', function ($sq) use ($filters): void {
                        $sq->where('name', 'ILIKE', "%{$filters->search}%");
                    });
            });
        }

        if ($filters->stone !== null) {
            $query->whereHas('stones', function ($q) use ($filters): void {
                $q->where('slug', $filters->stone);
            });
        }

        if ($filters->category !== null) {
            $query->whereHas('categories', function ($q) use ($filters): void {
                $q->where('slug', $filters->category);
            });
        }

        $query->orderByDesc('completed_year')->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }
}

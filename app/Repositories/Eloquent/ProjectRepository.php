<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

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
}

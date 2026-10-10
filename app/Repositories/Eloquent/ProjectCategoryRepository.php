<?php

namespace App\Repositories\Eloquent;

use App\Models\ProjectCategory;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectCategoryRepository implements ProjectCategoryRepositoryInterface
{
    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): Collection
    {
        return ProjectCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (ProjectCategory $category): array => [
                'value' => $category->slug,
                'label' => $category->name,
            ]);
    }
}

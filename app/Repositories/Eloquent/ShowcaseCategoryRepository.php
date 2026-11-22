<?php

namespace App\Repositories\Eloquent;

use App\Models\ShowcaseCategory;
use App\Repositories\Contracts\ShowcaseCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class ShowcaseCategoryRepository implements ShowcaseCategoryRepositoryInterface
{
    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): Collection
    {
        return ShowcaseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (ShowcaseCategory $category): array => [
                'value' => $category->slug,
                'label' => $category->name,
            ]);
    }
}

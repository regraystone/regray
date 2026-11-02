<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class BrandRepository implements BrandRepositoryInterface
{
    /** @return Collection<int, Brand> */
    public function active(): Collection
    {
        return Brand::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'logo_path', 'description']);
    }

    public function findActiveBySlug(string $slug): ?Brand
    {
        return Brand::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'projects' => fn (Relation $projects) => $projects->getQuery()
                    ->select(['id', 'brand_id', 'slug', 'name', 'thumbnail_path'])
                    ->where('is_published', true)
                    ->orderByDesc('completed_year')
                    ->orderBy('name'),
            ])
            ->first();
    }
}

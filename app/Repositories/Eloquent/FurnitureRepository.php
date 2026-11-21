<?php

namespace App\Repositories\Eloquent;

use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FurnitureRepository implements FurnitureRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Furniture
    {
        return Furniture::query()
            ->active()
            ->with(['brand:id,slug,name', 'furnitureType:id,slug,name'])
            ->where('slug', $slug)
            ->first();
    }

    public function activeOfBrand(int $brandId): Collection
    {
        return Furniture::query()
            ->active()
            ->where('brand_id', $brandId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);
    }

    public function relatedTo(Furniture $furniture, int $limit): Collection
    {
        return Furniture::query()
            ->active()
            ->with(['brand:id,slug,name', 'furnitureType:id,slug,name'])
            ->where('brand_id', $furniture->brand_id)
            ->where('furniture_type_id', $furniture->furniture_type_id)
            ->whereKeyNot($furniture->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}

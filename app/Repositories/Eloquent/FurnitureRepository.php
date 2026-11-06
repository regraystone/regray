<?php

namespace App\Repositories\Eloquent;

use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;

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
}

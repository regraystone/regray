<?php

namespace App\Repositories\Contracts;

use App\Data\FurnitureFilterData;
use App\Models\Furniture;
use Illuminate\Pagination\LengthAwarePaginator;

interface FurnitureRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Furniture> */
    public function paginate(FurnitureFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findActiveBySlug(string $slug): ?Furniture;
}

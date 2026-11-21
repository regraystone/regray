<?php

namespace App\Repositories\Contracts;

use App\Models\Furniture;
use Illuminate\Database\Eloquent\Collection;

interface FurnitureRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Furniture;

    /**
     * The brand's active pieces in their catalog order, for stepping from one
     * piece to the next.
     *
     * @return Collection<int, Furniture>
     */
    public function activeOfBrand(int $brandId): Collection;

    /**
     * Up to `$limit` other active pieces of the same brand and type, in
     * catalog order.
     *
     * @return Collection<int, Furniture>
     */
    public function relatedTo(Furniture $furniture, int $limit): Collection;
}

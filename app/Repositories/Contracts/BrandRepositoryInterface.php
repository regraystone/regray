<?php

namespace App\Repositories\Contracts;

use App\Data\BrandProductFilterData;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Project;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BrandRepositoryInterface
{
    /**
     * The active brands in their home page order.
     *
     * @return Collection<int, Brand>
     */
    public function active(): Collection;

    /**
     * One page of the brand's active products: its stones, its furniture, or
     * both in one list ordered by name.
     *
     * @return LengthAwarePaginator<int, Stone|Furniture>
     */
    public function paginateProducts(Brand $brand, BrandProductFilterData $filters): LengthAwarePaginator;

    /** Whether the brand has any active product, whatever the filters say. */
    public function hasActiveProducts(Brand $brand): bool;

    /**
     * The brand's active stones, each with its active types, and its active
     * furniture, each with its type: what its page says it offers.
     *
     * @return array{stones: Collection<int, Stone>, furniture: Collection<int, Furniture>}
     */
    public function activeProductsWithTypes(Brand $brand): array;

    /**
     * The published projects of the brand, or made with its stones, latest
     * first.
     *
     * @return Collection<int, Project>
     */
    public function publishedProjects(Brand $brand, int $limit): Collection;
}

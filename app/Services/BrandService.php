<?php

namespace App\Services;

use App\Data\BrandProductFilterData;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandService
{
    public function __construct(
        public readonly BrandRepositoryInterface $brandRepository,
    ) {}

    /** @return Collection<int, Brand> */
    public function listActive(): Collection
    {
        return $this->brandRepository->active();
    }

    /**
     * How many active stones and pieces of furniture the brand has: the "In
     * our catalog" line on its page.
     *
     * @return array{stones: int, furniture: int}
     */
    public function productCounts(Brand $brand): array
    {
        return $this->brandRepository->activeProductCounts($brand);
    }

    /**
     * The other active brands, in their home page order.
     *
     * @return Collection<int, Brand>
     */
    public function othersThan(Brand $brand): Collection
    {
        return $this->brandRepository->active()
            ->reject(fn (Brand $other): bool => $other->is($brand))
            ->values();
    }

    /**
     * @return array{products: LengthAwarePaginator<int, Stone|Furniture>, hasProducts: bool, filters: array{collection: string, search: string|null, type: string|null, size: string|null, thickness: string|null, furnitureType: string|null, perPage: int}}
     */
    public function listProducts(Brand $brand, BrandProductFilterData $filters): array
    {
        return [
            'products' => $this->brandRepository->paginateProducts($brand, $this->withEscapedSearch($filters)),
            'hasProducts' => $this->brandRepository->hasActiveProducts($brand),
            'filters' => [
                'collection' => $filters->collection,
                'search' => $filters->search,
                'type' => $filters->type,
                'size' => $filters->size,
                'thickness' => $filters->thickness,
                'furnitureType' => $filters->furnitureType,
                'perPage' => $filters->perPage,
            ],
        ];
    }

    /** A `%` or `_` someone typed is searched for, not treated as a wildcard. */
    private function withEscapedSearch(BrandProductFilterData $filters): BrandProductFilterData
    {
        return new BrandProductFilterData(
            $filters->collection,
            $filters->search === null ? null : str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters->search),
            $filters->type,
            $filters->size,
            $filters->thickness,
            $filters->furnitureType,
            $filters->page,
            $filters->perPage,
        );
    }
}

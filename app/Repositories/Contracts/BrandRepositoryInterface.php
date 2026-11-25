<?php

namespace App\Repositories\Contracts;

use App\Data\BrandProductFilterData;
use App\Models\Brand;
use App\Models\Furniture;
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
     * The active brands, as active() orders them, each with `has_products`:
     * whether it has an active stone or piece of furniture, so the Inquire
     * page offers only a brand it can supply.
     *
     * @return Collection<int, Brand>
     */
    public function activeForInquiry(): Collection;

    /**
     * The admin's Brands page: every brand but the archived, hidden ones too,
     * by display order then name, a page at a time. The search (already
     * escaped for ILIKE) matches the brand's name only. Each
     * comes with how many active stones and furniture it has.
     *
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator;

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
     * How many active stones and pieces of furniture the brand has.
     *
     * @return array{stones: int, furniture: int}
     */
    public function activeProductCounts(Brand $brand): array;
}

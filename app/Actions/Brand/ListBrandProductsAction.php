<?php

namespace App\Actions\Brand;

use App\Data\BrandProductFilterData;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Services\BrandService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListBrandProductsAction
{
    public function __construct(
        public readonly BrandService $brandService,
    ) {}

    /**
     * @return array{products: LengthAwarePaginator<int, Stone|Furniture>, hasProducts: bool, filters: array{collection: string, search: string|null, category: string|null, size: string|null, thickness: string|null, furnitureType: string|null, perPage: int}}
     */
    public function execute(Brand $brand, BrandProductFilterData $filters): array
    {
        return $this->brandService->listProducts($brand, $filters);
    }
}

<?php

namespace App\Services;

use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    public function getActiveBrand(string $slug): Brand
    {
        $brand = $this->brandRepository->findActiveBySlug($slug);

        if ($brand === null) {
            throw (new ModelNotFoundException)->setModel(Brand::class, [$slug]);
        }

        return $brand;
    }
}

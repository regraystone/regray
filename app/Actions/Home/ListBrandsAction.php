<?php

namespace App\Actions\Home;

use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Database\Eloquent\Collection;

class ListBrandsAction
{
    public function __construct(
        public readonly BrandService $brandService,
    ) {}

    /** @return Collection<int, Brand> */
    public function execute(): Collection
    {
        return $this->brandService->listActive();
    }
}

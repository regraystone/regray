<?php

namespace App\Actions\Brand;

use App\Models\Brand;
use App\Services\BrandService;

class ShowBrandAction
{
    public function __construct(
        public readonly BrandService $brandService,
    ) {}

    public function execute(string $slug): Brand
    {
        return $this->brandService->getActiveBrand($slug);
    }
}

<?php

namespace App\Http\Controllers\Brand;

use App\Actions\Brand\ListBrandProductsAction;
use App\Data\BrandProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\BrandCollectionRequest;
use App\Http\Resources\BrandProductResource;
use App\Http\Resources\BrandProfileResource;
use App\Http\Resources\LandingBrandResource;
use App\Models\Brand;
use App\Models\FurnitureType;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\BrandService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's page and its collection, for guests and signed-in users alike:
 * how many of its products are in the catalog, and the other brands of the
 * group.
 */
class BrandController extends Controller
{
    public function __construct(
        public readonly ListBrandProductsAction $listProducts,
        public readonly StoneRepositoryInterface $stoneRepository,
        public readonly BrandService $brandService,
    ) {}

    public function show(BrandCollectionRequest $request, Brand $brand): Response
    {
        $result = $this->listProducts->execute($brand, BrandProductFilterData::fromRequest($request));

        return Inertia::render('brand-profile', [
            'brand' => new BrandProfileResource($brand->load(Brand::DETAILS)),
            'products' => Inertia::scroll(BrandProductResource::collection($result['products'])),
            'hasProducts' => $result['hasProducts'],
            'filters' => $result['filters'],
            'filterOptions' => Inertia::once(fn () => [
                ...$this->stoneRepository->filterOptions(),
                'furnitureTypes' => FurnitureType::query()->active()->orderBy('sort_order')->orderBy('name')->get(['slug', 'name'])
                    ->map(fn (FurnitureType $type): array => ['value' => $type->slug, 'label' => $type->name])
                    ->all(),
            ]),
            'offer' => $this->brandService->productCounts($brand),
            'otherBrands' => LandingBrandResource::collection($this->brandService->othersThan($brand))->resolve($request),
        ]);
    }
}

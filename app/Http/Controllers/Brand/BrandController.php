<?php

namespace App\Http\Controllers\Brand;

use App\Actions\Brand\ListBrandProductsAction;
use App\Data\BrandProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\BrandCollectionRequest;
use App\Http\Resources\BrandProductResource;
use App\Http\Resources\BrandProfileResource;
use App\Models\Brand;
use App\Models\Furniture;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's page and its collection, for guests and signed-in users alike.
 */
class BrandController extends Controller
{
    public function __construct(
        public readonly ListBrandProductsAction $listProducts,
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function show(BrandCollectionRequest $request, Brand $brand): Response
    {
        $result = $this->listProducts->execute($brand, BrandProductFilterData::fromRequest($request));

        return Inertia::render('brand-profile', [
            'brand' => new BrandProfileResource($brand),
            'products' => Inertia::scroll(BrandProductResource::collection($result['products'])),
            'hasProducts' => $result['hasProducts'],
            'filters' => $result['filters'],
            'filterOptions' => Inertia::once(fn () => [
                ...$this->stoneRepository->filterOptions(),
                'furnitureTypes' => collect(Furniture::TYPES)->map(fn (string $type) => [
                    'value' => $type,
                    'label' => $type,
                ])->all(),
            ]),
        ]);
    }
}

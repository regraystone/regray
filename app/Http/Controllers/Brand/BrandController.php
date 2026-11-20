<?php

namespace App\Http\Controllers\Brand;

use App\Actions\Brand\ListBrandProductsAction;
use App\Data\BrandProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\BrandCollectionRequest;
use App\Http\Resources\BrandProductResource;
use App\Http\Resources\BrandProfileResource;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingProjectResource;
use App\Models\Brand;
use App\Models\FurnitureType;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\BrandService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's page and its collection, for guests and signed-in users alike:
 * what it offers, counted from its products, the projects of it or made with
 * its stones, and the other brands of the group.
 */
class BrandController extends Controller
{
    /** How many projects the brand's page shows. */
    private const PROJECTS = 4;

    public function __construct(
        public readonly ListBrandProductsAction $listProducts,
        public readonly StoneRepositoryInterface $stoneRepository,
        public readonly BrandService $brandService,
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
                'furnitureTypes' => FurnitureType::query()->active()->orderBy('sort_order')->pluck('name')
                    ->map(fn (string $type): array => ['value' => $type, 'label' => $type])
                    ->all(),
            ]),
            'offer' => $this->brandService->offerings($brand),
            'projects' => LandingProjectResource::collection($this->brandService->publishedProjects($brand, self::PROJECTS))->resolve($request),
            'otherBrands' => LandingBrandResource::collection($this->brandService->othersThan($brand))->resolve($request),
        ]);
    }
}

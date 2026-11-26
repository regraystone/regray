<?php

namespace App\Http\Controllers\Brand;

use App\Actions\Brand\ListBrandProductsAction;
use App\Data\BrandProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\BrandCollectionRequest;
use App\Http\Resources\BrandProductResource;
use App\Http\Resources\BrandProfileResource;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingShowcaseResource;
use App\Models\Brand;
use App\Models\FurnitureType;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\BrandService;
use App\Services\ShowcaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's page and its collection, for guests and signed-in users alike:
 * how many of its products are in the catalog, and the other brands of the
 * group.
 */
class BrandController extends Controller
{
    /** How many of the brand's finished projects its Company profile shows. */
    private const PROFILE_SHOWCASES = 6;

    public function __construct(
        public readonly ListBrandProductsAction $listProducts,
        public readonly StoneRepositoryInterface $stoneRepository,
        public readonly BrandService $brandService,
        public readonly ShowcaseService $showcaseService,
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

    /**
     * A brand's Company profile: who it is, in figures (its active products
     * and its finished projects, counted), and its latest finished projects.
     * Only a brand with one (Brand::COMPANY_PROFILES) has the page.
     */
    public function companyProfile(Request $request, Brand $brand): Response
    {
        abort_unless($brand->hasCompanyProfile(), 404);

        $showcases = $this->showcaseService->latestPublishedForBrand($brand, self::PROFILE_SHOWCASES);

        return Inertia::render('brand-company-profile', [
            'brand' => new BrandProfileResource($brand->load(Brand::DETAILS)),
            'counts' => [
                ...$this->brandService->productCounts($brand),
                'showcases' => $showcases['count'],
            ],
            'showcases' => LandingShowcaseResource::collection($showcases['latest'])->resolve($request),
        ]);
    }
}

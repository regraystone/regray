<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Actions\Home\ListFurnitureAction;
use App\Actions\Home\ListStonesAction;
use App\Data\FurnitureFilterData;
use App\Data\StoneFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\FurnitureIndexRequest;
use App\Http\Requests\Home\StoneIndexRequest;
use App\Http\Resources\BrandCardResource;
use App\Http\Resources\FurnitureCardResource;
use App\Http\Resources\StoneCardResource;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: guests see the catalogue, signed-in users redirect to their role home.
 * Reuses the same catalogue logic as the signed-in home page.
 */
class LandingController extends Controller
{
    public function __construct(
        public readonly ListStonesAction $listStones,
        public readonly ListFurnitureAction $listFurniture,
        public readonly ListBrandsAction $listBrands,
        public readonly StoneRepositoryInterface $stoneRepository,
        public readonly ProjectCategoryRepositoryInterface $projectCategoryRepository,
    ) {}

    public function index(Request $request): Response | RedirectResponse
    {
        // Signed-in users are redirected to their role home.
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        if ($request->query('collection') === 'furniture') {
            return $this->indexFurniture(app(FurnitureIndexRequest::class));
        }

        return $this->indexStones(app(StoneIndexRequest::class));
    }

    private function indexStones(StoneIndexRequest $request): Response
    {
        $result = $this->listStones->execute(StoneFilterData::fromRequest($request));

        return Inertia::render('landing', [
            ...$this->sharedProps($request),
            'stones' => Inertia::scroll(StoneCardResource::collection($result['stones'])),
            'filters' => [...$result['filters'], 'projectCategory' => null, 'collection' => 'stones'],
        ]);
    }

    private function indexFurniture(FurnitureIndexRequest $request): Response
    {
        $result = $this->listFurniture->execute(FurnitureFilterData::fromRequest($request));

        return Inertia::render('landing', [
            ...$this->sharedProps($request),
            'furniture' => Inertia::scroll(FurnitureCardResource::collection($result['furniture'])),
            'filters' => [
                ...$result['filters'],
                'category' => null,
                'size' => null,
                'thickness' => null,
                'collection' => 'furniture',
            ],
        ]);
    }

    /**
     * The same in both collections, so each is sent once and kept across
     * filter changes: the second select's options for either collection,
     * and the brands.
     *
     * @return array<string, mixed>
     */
    private function sharedProps(Request $request): array
    {
        return [
            'filterOptions' => Inertia::once(fn () => [
                ...$this->stoneRepository->filterOptions(),
                'projectTypes' => $this->projectCategoryRepository->activeFilterOptions(),
            ]),
            'brands' => Inertia::once(fn () => BrandCardResource::collection($this->listBrands->execute())->resolve($request)),
        ];
    }
}

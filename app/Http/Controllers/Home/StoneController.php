<?php

namespace App\Http\Controllers\Home;

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
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page: the brands, then one collection at a time, Natural Stone
 * (the default) or Furniture (`?collection=furniture`).
 */
class StoneController extends Controller
{
    public function __construct(
        public readonly ListStonesAction $listStones,
        public readonly ListFurnitureAction $listFurniture,
        public readonly ListBrandsAction $listBrands,
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function index(Request $request): Response
    {
        if ($request->query('collection') === 'furniture') {
            return $this->indexFurniture(app(FurnitureIndexRequest::class));
        }

        return $this->indexStones(app(StoneIndexRequest::class));
    }

    private function indexStones(StoneIndexRequest $request): Response
    {
        $result = $this->listStones->execute(StoneFilterData::fromRequest($request));

        return Inertia::render('home', [
            ...$this->sharedProps($request),
            'stones' => Inertia::scroll(StoneCardResource::collection($result['stones'])),
            'filters' => [...$result['filters'], 'furnitureType' => null, 'collection' => 'stones'],
        ]);
    }

    private function indexFurniture(FurnitureIndexRequest $request): Response
    {
        $result = $this->listFurniture->execute(FurnitureFilterData::fromRequest($request));

        return Inertia::render('home', [
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
     * @return array<string, mixed>
     */
    private function sharedProps(Request $request): array
    {
        return [
            'filterOptions' => Inertia::once(fn () => [
                ...$this->stoneRepository->filterOptions(),
                'furnitureTypes' => collect(FurnitureIndexRequest::TYPES)->map(fn (string $type) => [
                    'value' => $type,
                    'label' => $type,
                ])->all(),
            ]),
            'brands' => Inertia::once(fn () => BrandCardResource::collection($this->listBrands->execute())->resolve($request)),
        ];
    }
}

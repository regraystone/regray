<?php

namespace App\Http\Controllers\Furniture;

use App\Actions\Furniture\ShowFurnitureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Furniture\FurnitureShowRequest;
use App\Http\Resources\FurnitureCardResource;
use App\Http\Resources\FurnitureProfileResource;
use App\Models\Brand;
use App\Models\Furniture;
use App\Services\FurnitureCustomizationService;
use App\Services\FurnitureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A piece of furniture's page: the piece, what a client may choose for it
 * (FurnitureCustomizationService), the pieces before and after it in its
 * brand, and more of the same type.
 */
class FurnitureController extends Controller
{
    /** How many other pieces of the same type the page shows. */
    private const RELATED_COUNT = 4;

    public function __construct(
        public readonly ShowFurnitureAction $showFurniture,
        public readonly FurnitureService $furnitureService,
        public readonly FurnitureCustomizationService $customization,
    ) {}

    /** The Saved door, which finds a piece by its slug alone. */
    public function show(FurnitureShowRequest $request): Response
    {
        return $this->profile($request, $request->validated('furniture'));
    }

    /** The brand's own URL: the scoped binding has already matched the piece to this brand. */
    public function showForBrand(Request $request, Brand $brand, Furniture $furniture): Response
    {
        return $this->profile($request, $furniture->slug);
    }

    private function profile(Request $request, string $slug): Response
    {
        $furniture = $this->showFurniture->execute($slug);
        $neighbours = $this->furnitureService->neighbours($furniture);
        $step = fn (?Furniture $piece): ?array => $piece === null ? null : ['slug' => $piece->slug, 'name' => $piece->name];

        return Inertia::render('furniture-profile', [
            'furniture' => new FurnitureProfileResource($furniture),
            'customization' => $this->customization->for($furniture),
            'previous' => $step($neighbours['previous']),
            'next' => $step($neighbours['next']),
            'related' => FurnitureCardResource::collection($this->furnitureService->related($furniture, self::RELATED_COUNT))->resolve($request),
        ]);
    }
}

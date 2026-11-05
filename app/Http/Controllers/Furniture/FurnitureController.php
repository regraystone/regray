<?php

namespace App\Http\Controllers\Furniture;

use App\Actions\Furniture\ShowFurnitureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Furniture\FurnitureShowRequest;
use App\Http\Resources\FurnitureProfileResource;
use App\Models\Brand;
use App\Models\Furniture;
use Inertia\Inertia;
use Inertia\Response;

class FurnitureController extends Controller
{
    public function __construct(
        public readonly ShowFurnitureAction $showFurniture,
    ) {}

    /** The Saved door, which finds a piece by its slug alone. */
    public function show(FurnitureShowRequest $request): Response
    {
        return $this->profile($request->validated('furniture'));
    }

    /** The brand's own URL: the scoped binding has already matched the piece to this brand. */
    public function showForBrand(Brand $brand, Furniture $furniture): Response
    {
        return $this->profile($furniture->slug);
    }

    private function profile(string $slug): Response
    {
        return Inertia::render('furniture-profile', [
            'furniture' => new FurnitureProfileResource($this->showFurniture->execute($slug)),
        ]);
    }
}

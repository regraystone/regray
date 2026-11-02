<?php

namespace App\Http\Controllers\Furniture;

use App\Actions\Furniture\ShowFurnitureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Furniture\FurnitureShowRequest;
use App\Http\Resources\FurnitureProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class FurnitureController extends Controller
{
    public function __construct(
        public readonly ShowFurnitureAction $showFurniture,
    ) {}

    public function show(FurnitureShowRequest $request): Response
    {
        $furniture = $this->showFurniture->execute($request->validated('furniture'));

        return Inertia::render('furniture-profile', [
            'furniture' => new FurnitureProfileResource($furniture),
        ]);
    }
}

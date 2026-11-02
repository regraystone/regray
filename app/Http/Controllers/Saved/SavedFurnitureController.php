<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Saved\SaveFurnitureAction;
use App\Actions\Saved\UnsaveFurnitureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\SavedFurnitureRequest;
use Illuminate\Http\RedirectResponse;

/** Saving and unsaving furniture, the twin of SavedStoneController's writes. */
class SavedFurnitureController extends Controller
{
    public function __construct(
        public readonly SaveFurnitureAction $saveFurniture,
        public readonly UnsaveFurnitureAction $unsaveFurniture,
    ) {}

    public function store(SavedFurnitureRequest $request): RedirectResponse
    {
        $this->saveFurniture->execute($request->user(), $request->validated('furniture'));

        return back();
    }

    public function destroy(SavedFurnitureRequest $request): RedirectResponse
    {
        $this->unsaveFurniture->execute($request->user(), $request->validated('furniture'));

        return back();
    }
}

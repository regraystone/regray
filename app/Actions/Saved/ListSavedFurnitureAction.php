<?php

namespace App\Actions\Saved;

use App\Models\Furniture;
use App\Models\User;
use App\Services\SavedFurnitureService;
use Illuminate\Database\Eloquent\Collection;

class ListSavedFurnitureAction
{
    public function __construct(
        public readonly SavedFurnitureService $savedFurnitureService,
    ) {}

    /** @return Collection<int, Furniture> */
    public function execute(User $user): Collection
    {
        return $this->savedFurnitureService->listFor($user);
    }
}

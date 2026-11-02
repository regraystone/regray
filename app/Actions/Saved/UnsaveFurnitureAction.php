<?php

namespace App\Actions\Saved;

use App\Models\User;
use App\Services\SavedFurnitureService;

class UnsaveFurnitureAction
{
    public function __construct(
        public readonly SavedFurnitureService $savedFurnitureService,
    ) {}

    public function execute(User $user, string $slug): void
    {
        $this->savedFurnitureService->remove($user, $slug);
    }
}

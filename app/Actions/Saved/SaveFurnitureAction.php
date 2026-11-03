<?php

namespace App\Actions\Saved;

use App\Models\User;
use App\Services\SavedFurnitureService;

class SaveFurnitureAction
{
    public function __construct(
        public readonly SavedFurnitureService $savedFurnitureService,
    ) {}

    public function execute(User $user, string $slug): void
    {
        $this->savedFurnitureService->save($user, $slug);
    }
}

<?php

namespace App\Actions\Furniture;

use App\Models\Furniture;
use App\Services\FurnitureService;

class ShowFurnitureAction
{
    public function __construct(
        public readonly FurnitureService $furnitureService,
    ) {}

    public function execute(string $slug): Furniture
    {
        return $this->furnitureService->getActiveFurniture($slug);
    }
}

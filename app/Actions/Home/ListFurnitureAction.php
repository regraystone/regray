<?php

namespace App\Actions\Home;

use App\Data\FurnitureFilterData;
use App\Models\Furniture;
use App\Services\FurnitureService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListFurnitureAction
{
    public function __construct(
        public readonly FurnitureService $furnitureService,
    ) {}

    /**
     * @return array{furniture: LengthAwarePaginator<int, Furniture>, filters: array{search: string|null, projectCategory: string|null, perPage: int}}
     */
    public function execute(FurnitureFilterData $filters): array
    {
        return $this->furnitureService->listFurniture($filters);
    }
}

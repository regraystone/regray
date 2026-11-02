<?php

namespace App\Actions\Home;

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Services\StoneService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListStonesAction
{
    public function __construct(
        public readonly StoneService $stoneService,
    ) {}

    /**
     * @return array{stones: LengthAwarePaginator<int, Stone>, filters: array{search: string|null, category: string|null, size: string|null, thickness: string|null, perPage: int}}
     */
    public function execute(StoneFilterData $filters): array
    {
        return $this->stoneService->listStones($filters);
    }
}

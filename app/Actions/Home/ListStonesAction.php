<?php

namespace App\Actions\Home;

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Services\StoneService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListStonesAction
{
    public function __construct(
        public readonly StoneService $stoneService,
    ) {}

    /**
     * @return array{stones: LengthAwarePaginator<int, Stone>, filters: array{search: string|null, category: string|null, size: string|null, thickness: string|null, perPage: int}, filterOptions: array{categories: Collection<int, array{value: string, label: string}>, sizes: Collection<int, array{value: string, label: string}>, thicknesses: Collection<int, array{value: string, label: string}>}}
     */
    public function execute(StoneFilterData $filters): array
    {
        return $this->stoneService->listStones($filters);
    }
}

<?php

namespace App\Services;

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class StoneService
{
    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function getActiveStone(string $stoneSlug): Stone
    {
        $stone = $this->stoneRepository->findActiveBySlug($stoneSlug);

        if ($stone === null) {
            throw (new ModelNotFoundException)->setModel(Stone::class, [$stoneSlug]);
        }

        return $stone;
    }

    /**
     * @return array{stones: LengthAwarePaginator<int, Stone>, filters: array{search: string|null, category: string|null, size: string|null, thickness: string|null, perPage: int}}
     */
    public function listStones(StoneFilterData $filters): array
    {
        $cleaned = $this->cleanFilters($filters);

        return [
            'stones' => $this->stoneRepository->paginate($cleaned, $cleaned->perPage),
            'filters' => [
                'search' => $cleaned->search,
                'category' => $cleaned->category,
                'size' => $cleaned->size,
                'thickness' => $cleaned->thickness,
                'perPage' => $cleaned->perPage,
            ],
        ];
    }

    public function cleanFilters(StoneFilterData $filters): StoneFilterData
    {
        $search = $filters->search === null ? null : trim($filters->search);
        if ($search === '') {
            $search = null;
        } elseif ($search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
        }

        return new StoneFilterData($search, $filters->category, $filters->size, $filters->thickness, $filters->page, $filters->perPage);
    }
}

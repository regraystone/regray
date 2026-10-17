<?php

namespace App\Services;

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StoneService
{
    public const PER_PAGE = 12;

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
     * @return array{stones: LengthAwarePaginator<int, Stone>, filters: array{search: string|null, category: string|null, size: string|null, thickness: string|null}, filterOptions: array{categories: Collection<int, array{value: string, label: string}>, sizes: Collection<int, array{value: string, label: string}>, thicknesses: Collection<int, array{value: string, label: string}>}}
     */
    public function listStones(StoneFilterData $filters): array
    {
        $cleaned = $this->cleanFilters($filters);

        return [
            'stones' => $this->stoneRepository->paginate($cleaned, self::PER_PAGE),
            'filters' => [
                'search' => $cleaned->search,
                'category' => $cleaned->category,
                'size' => $cleaned->size,
                'thickness' => $cleaned->thickness,
            ],
            'filterOptions' => $this->stoneRepository->filterOptions(),
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

        return new StoneFilterData($search, $filters->category, $filters->size, $filters->thickness, $filters->page);
    }
}

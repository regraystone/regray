<?php

namespace App\Services;

use App\Data\FurnitureFilterData;
use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class FurnitureService
{
    public function __construct(
        public readonly FurnitureRepositoryInterface $furnitureRepository,
    ) {}

    public function getActiveFurniture(string $slug): Furniture
    {
        $furniture = $this->furnitureRepository->findActiveBySlug($slug);

        if ($furniture === null) {
            throw (new ModelNotFoundException)->setModel(Furniture::class, [$slug]);
        }

        return $furniture;
    }

    /**
     * @return array{furniture: LengthAwarePaginator<int, Furniture>, filters: array{search: string|null, furnitureType: string|null, perPage: int}}
     */
    public function listFurniture(FurnitureFilterData $filters): array
    {
        $cleaned = $this->cleanFilters($filters);

        return [
            'furniture' => $this->furnitureRepository->paginate($cleaned, $cleaned->perPage),
            'filters' => [
                'search' => $cleaned->search,
                'furnitureType' => $cleaned->furnitureType,
                'perPage' => $cleaned->perPage,
            ],
        ];
    }

    public function cleanFilters(FurnitureFilterData $filters): FurnitureFilterData
    {
        $search = $filters->search === null ? null : trim($filters->search);
        if ($search === '') {
            $search = null;
        } elseif ($search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
        }

        return new FurnitureFilterData($search, $filters->furnitureType, $filters->page, $filters->perPage);
    }
}

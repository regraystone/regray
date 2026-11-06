<?php

namespace App\Services;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\Stone;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InquiryCatalogService
{
    public function __construct(
        public readonly InquiryCatalogRepositoryInterface $catalogRepository,
    ) {}

    /**
     * Everything the Inquire page offers, read once.
     *
     * Locations come from config rather than the database: validation reads the
     * same list, so the two cannot disagree about where a project may be.
     *
     * @return array{services: Collection<int, Service>, furnitureTypes: Collection<int, FurnitureType>, stones: Collection<int, Stone>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>}
     */
    public function options(): array
    {
        return [
            'services' => $this->catalogRepository->services(),
            'furnitureTypes' => $this->catalogRepository->furnitureTypes(),
            'stones' => $this->catalogRepository->stones(),
            'edgeProfiles' => $this->catalogRepository->activeEdgeProfiles(),
            'finishes' => $this->catalogRepository->activeFinishes(),
            'locations' => config('inquiry.locations'),
        ];
    }
}

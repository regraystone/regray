<?php

namespace App\Services;

use App\Data\InquiryStoneFilterData;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ServiceType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InquiryCatalogService
{
    public function __construct(
        public readonly InquiryCatalogRepositoryInterface $catalogRepository,
    ) {}

    /**
     * Everything the Inquire page offers but its stones, read once.
     *
     * Locations come from config rather than the database: validation reads the
     * same list, so the two cannot disagree about where a project may be.
     *
     * @return array{serviceTypes: Collection<int, ServiceType>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>}
     */
    public function options(): array
    {
        return [
            'serviceTypes' => $this->catalogRepository->serviceTypes(),
            'edgeProfiles' => $this->catalogRepository->activeEdgeProfiles(),
            'finishes' => $this->catalogRepository->activeFinishes(),
            'locations' => config('inquiry.locations'),
        ];
    }

    /**
     * The service types the Inquire page offers, each with its services: the
     * landing page lists the same ones.
     *
     * @return Collection<int, ServiceType>
     */
    public function serviceTypes(): Collection
    {
        return $this->catalogRepository->serviceTypes();
    }

    /**
     * One page of the stones the Inquire page offers.
     *
     * @return LengthAwarePaginator<int, Stone>
     */
    public function stones(User $user, InquiryStoneFilterData $filters): LengthAwarePaginator
    {
        return $this->catalogRepository->paginateStones($user, $filters);
    }

    /**
     * The stone types the Inquire page filters by under this service.
     *
     * @return Collection<int, StoneType>
     */
    public function stoneTypes(?string $serviceSlug): Collection
    {
        return $this->catalogRepository->stoneTypes($serviceSlug);
    }

    /**
     * The stones of a restored draft or an edit, those still offered for its
     * service.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Stone>
     */
    public function stonesBySlugs(array $slugs, ?string $serviceSlug): Collection
    {
        return $this->catalogRepository->stonesBySlugs($slugs, $serviceSlug);
    }
}

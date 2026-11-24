<?php

namespace App\Services;

use App\Data\InquiryStoneFilterData;
use App\Models\Brand;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ServiceType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InquiryCatalogService
{
    public function __construct(
        public readonly InquiryCatalogRepositoryInterface $catalogRepository,
        public readonly BrandRepositoryInterface $brandRepository,
    ) {}

    /**
     * Everything the Inquire page offers but its stones, read once: the
     * services, the active brands in their home page order (the Brand
     * question and the stones' Brand filter), and the edge profiles and
     * finishes still in use.
     *
     * @return array{serviceTypes: Collection<int, ServiceType>, brands: Collection<int, Brand>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>}
     */
    public function options(): array
    {
        return [
            'serviceTypes' => $this->catalogRepository->serviceTypes(),
            'brands' => $this->brandRepository->activeForInquiry(),
            'edgeProfiles' => $this->catalogRepository->activeEdgeProfiles(),
            'finishes' => $this->catalogRepository->activeFinishes(),
        ];
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

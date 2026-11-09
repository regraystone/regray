<?php

namespace App\Repositories\Contracts;

use App\Data\InquiryStoneFilterData;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InquiryCatalogRepositoryInterface
{
    /**
     * The service types the page offers, each with its active services, both in
     * the order it lists them. A service made from no stone type is left out,
     * and so is a type with no service left.
     *
     * @return Collection<int, ServiceType>
     */
    public function serviceTypes(): Collection;

    /**
     * One page of the active stones offered for the filter's service, with what
     * their cards show. None without a service.
     *
     * @return LengthAwarePaginator<int, Stone>
     */
    public function paginateStones(User $user, InquiryStoneFilterData $filters): LengthAwarePaginator;

    /**
     * The stone types linked to this service that have an active stone for it.
     * None without a service.
     *
     * @return Collection<int, StoneType>
     */
    public function stoneTypes(?string $serviceSlug): Collection;

    /**
     * The active stones among these slugs that are offered for this service,
     * with what their cards show. None without a service.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Stone>
     */
    public function stonesBySlugs(array $slugs, ?string $serviceSlug): Collection;

    /** The service with this slug, offered or not, so an edit's own still resolves. */
    public function serviceBySlug(?string $slug): ?Service;

    /** @return Collection<int, EdgeProfile> */
    public function activeEdgeProfiles(): Collection;

    /** @return Collection<int, Finish> */
    public function activeFinishes(): Collection;
}

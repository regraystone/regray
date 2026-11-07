<?php

namespace App\Repositories\Contracts;

use App\Data\InquiryStoneFilterData;
use App\Models\EdgeProfile;
use App\Models\Finish;
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
     * the order it lists them. A type with no active service is left out.
     *
     * @return Collection<int, ServiceType>
     */
    public function serviceTypes(): Collection;

    /**
     * One page of the active stones, with what their cards show.
     *
     * @return LengthAwarePaginator<int, Stone>
     */
    public function paginateStones(User $user, InquiryStoneFilterData $filters): LengthAwarePaginator;

    /**
     * The stone types the active stones have.
     *
     * @return Collection<int, StoneType>
     */
    public function stoneTypes(): Collection;

    /**
     * The active stones among these slugs, with what their cards show.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Stone>
     */
    public function stonesBySlugs(array $slugs): Collection;

    /** @return Collection<int, EdgeProfile> */
    public function activeEdgeProfiles(): Collection;

    /** @return Collection<int, Finish> */
    public function activeFinishes(): Collection;
}

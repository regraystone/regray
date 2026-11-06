<?php

namespace App\Repositories\Contracts;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;

interface InquiryCatalogRepositoryInterface
{
    /**
     * The services the page offers, in the order it lists them.
     *
     * @return Collection<int, Service>
     */
    public function services(): Collection;

    /**
     * The furniture types the page offers.
     *
     * @return Collection<int, FurnitureType>
     */
    public function furnitureTypes(): Collection;

    /**
     * Active stones with the category facts the Inquire page shows.
     *
     * @return Collection<int, Stone>
     */
    public function stones(): Collection;

    /** @return Collection<int, EdgeProfile> */
    public function activeEdgeProfiles(): Collection;

    /** @return Collection<int, Finish> */
    public function activeFinishes(): Collection;
}

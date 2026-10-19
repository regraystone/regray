<?php

namespace App\Repositories\Contracts;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;

interface InquiryCatalogRepositoryInterface
{
    /**
     * Active services with their features.
     *
     * @return Collection<int, Service>
     */
    public function services(): Collection;

    /**
     * Active stones with the category facts the Inquire page shows.
     *
     * @return Collection<int, Stone>
     */
    public function stones(): Collection;

    /**
     * The options of the Application step, which are the active project categories.
     *
     * @return Collection<int, ProjectCategory>
     */
    public function applications(): Collection;

    /** @return Collection<int, EdgeProfile> */
    public function activeEdgeProfiles(): Collection;

    /** @return Collection<int, Finish> */
    public function activeFinishes(): Collection;
}

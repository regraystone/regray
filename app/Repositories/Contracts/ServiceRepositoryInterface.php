<?php

namespace App\Repositories\Contracts;

use App\Models\DetailField;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceRepositoryInterface
{
    /**
     * The services still on the admin's All services page, offered or not, a
     * page at a time: by their type's display order, then their own. Searched
     * by name or description (already escaped for ILIKE), filtered by their
     * type's slug and whether they are visible.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginateForAdmin(?string $escapedSearch, ?string $typeSlug, ?bool $active, int $perPage, int $page): LengthAwarePaginator;

    /**
     * The service types a service can be under, hidden ones too, in their
     * display order.
     *
     * @return Collection<int, ServiceType>
     */
    public function typeOptions(): Collection;

    /**
     * Every question a service can ask, with its active listed answers.
     *
     * @return Collection<int, DetailField>
     */
    public function questions(): Collection;

    /**
     * The stone types a service can be made from.
     *
     * @return Collection<int, StoneType>
     */
    public function activeStoneTypes(): Collection;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\DetailField;
use App\Models\ServiceType;
use App\Models\StoneType;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class ServiceRepository implements ServiceRepositoryInterface
{
    /** @return Collection<int, ServiceType> */
    public function typesForAdmin(): Collection
    {
        return ServiceType::query()
            ->notArchived()
            ->with([
                'services' => fn (Relation $services) => $services->getQuery()
                    ->whereNull('services.archived_at')
                    ->withCount('inquiries')
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'services.features',
                'services.stoneTypes' => fn (Relation $types) => $types->getQuery()
                    ->select(['stone_types.id', 'stone_types.name'])
                    ->orderBy('stone_types.sort_order'),
                'services.detailFields:detail_fields.id,detail_fields.name,detail_fields.scope',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            // Each service reads its type (Service::isOffered()); it is the one loaded.
            ->each(fn (ServiceType $type) => $type->services->each->setRelation('serviceType', $type));
    }

    /** @return Collection<int, DetailField> */
    public function questions(): Collection
    {
        return DetailField::query()
            ->notArchived()
            ->with(['options' => fn (Relation $options) => $options->getQuery()->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, StoneType> */
    public function activeStoneTypes(): Collection
    {
        return StoneType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}

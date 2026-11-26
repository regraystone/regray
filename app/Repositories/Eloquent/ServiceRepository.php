<?php

namespace App\Repositories\Eloquent;

use App\Models\DetailField;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\StoneType;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceRepository implements ServiceRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Service> */
    public function paginateForAdmin(?string $escapedSearch, ?string $typeSlug, ?bool $active, int $perPage, int $page): LengthAwarePaginator
    {
        return Service::query()
            ->select('services.*')
            ->join('service_types', 'service_types.id', '=', 'services.service_type_id')
            ->whereNull('services.archived_at')
            ->whereNull('service_types.archived_at')
            ->when($typeSlug !== null, fn (Builder $query) => $query->where('service_types.slug', $typeSlug))
            ->when($active !== null, fn (Builder $query) => $query->where('services.is_active', $active))
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('services.name', 'ILIKE', "%{$escapedSearch}%")
                ->orWhere('services.description', 'ILIKE', "%{$escapedSearch}%")))
            ->with([
                // Service::isOffered() reads its type.
                'serviceType:id,name,is_active',
                'features',
                'stoneTypes' => fn (Relation $types) => $types->getQuery()
                    ->select(['stone_types.id', 'stone_types.name'])
                    ->orderBy('stone_types.sort_order'),
                'detailFields:detail_fields.id,detail_fields.name,detail_fields.scope',
            ])
            ->withCount('inquiries')
            ->orderBy('service_types.sort_order')
            ->orderBy('service_types.name')
            ->orderBy('services.sort_order')
            ->orderBy('services.name')
            ->orderBy('services.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return Collection<int, ServiceType> */
    public function typeOptions(): Collection
    {
        return ServiceType::query()
            ->notArchived()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'is_active']);
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

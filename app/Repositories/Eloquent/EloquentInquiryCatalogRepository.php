<?php

namespace App\Repositories\Eloquent;

use App\Data\InquiryStoneFilterData;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\BestSellerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class EloquentInquiryCatalogRepository implements InquiryCatalogRepositoryInterface
{
    /** What a stone card reads. */
    private const CARD_RELATIONS = ['type:id,slug,name', 'brand:id,slug,name'];

    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function serviceTypes(): Collection
    {
        return ServiceType::query()
            ->where('is_active', true)
            ->whereHas('services', fn (Builder $services) => $services->where('is_active', true)->whereHas('stoneTypes'))
            ->with(['services' => fn (Relation $services) => $services->getQuery()
                ->where('is_active', true)
                // A service made from no stone type is not offered (Service::isOffered()).
                ->whereHas('stoneTypes')
                ->orderBy('sort_order')
                ->orderBy('id'),
                // In the order the stone types are listed everywhere else.
                'services.stoneTypes' => fn (Relation $types) => $types->getQuery()
                    ->select(['stone_types.id', 'stone_types.slug'])
                    ->orderBy('stone_types.sort_order')
                    ->orderBy('stone_types.id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'name']);
    }

    public function paginateStones(User $user, InquiryStoneFilterData $filters): LengthAwarePaginator
    {
        $pattern = $filters->search === null ? null : '%'.addcslashes($filters->search, '\\%_').'%';
        $service = $this->serviceBySlug($filters->service);

        return Stone::query()
            ->active()
            ->with(self::CARD_RELATIONS)
            // No service, no stones: which stones fit is the service's to say.
            ->when($service === null, fn (Builder $query) => $query->whereRaw('false'))
            ->when($service !== null, fn (Builder $query) => $query->offeredFor($service))
            ->when($filters->tab === InquiryStoneFilterData::TAB_SAVED, fn (Builder $query) => $query
                ->whereHas('savedBy', fn (Builder $saved) => $saved->whereKey($user->id)))
            ->when($filters->tab === InquiryStoneFilterData::TAB_BEST_SELLERS, fn (Builder $query) => $query
                ->whereIn('stone_type_id', $this->stoneRepository->bestSellerTypeIds(BestSellerService::BEST_SELLER_COUNT)))
            ->when($filters->type !== null, fn (Builder $query) => $query
                ->whereHas('type', fn (Builder $type) => $type->where('slug', $filters->type)))
            ->when($pattern !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ILIKE', $pattern)
                ->orWhere('origin', 'ILIKE', $pattern)
                ->orWhereHas('type', fn (Builder $type) => $type->where('name', 'ILIKE', $pattern))))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function stoneTypes(?string $serviceSlug): Collection
    {
        $service = $this->serviceBySlug($serviceSlug);

        if ($service === null) {
            return new Collection;
        }

        return StoneType::query()
            ->whereIn('id', Stone::query()->offeredFor($service)->select('stone_type_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);
    }

    public function stonesBySlugs(array $slugs, ?string $serviceSlug): Collection
    {
        $service = $this->serviceBySlug($serviceSlug);

        if ($service === null) {
            return new Collection;
        }

        return Stone::query()
            ->offeredFor($service)
            ->with(self::CARD_RELATIONS)
            ->whereIn('slug', $slugs)
            ->get();
    }

    public function serviceBySlug(?string $slug): ?Service
    {
        return $slug === null ? null : Service::query()->where('slug', $slug)->first();
    }

    public function activeEdgeProfiles(): Collection
    {
        return EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'is_advisory']);
    }

    public function activeFinishes(): Collection
    {
        return Finish::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'is_advisory']);
    }
}

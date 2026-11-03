<?php

namespace App\Repositories\Eloquent;

use App\Data\FurnitureFilterData;
use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FurnitureRepository implements FurnitureRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Furniture> */
    public function paginate(FurnitureFilterData $filters, int $perPage): LengthAwarePaginator
    {
        $query = Furniture::query()->active();

        if ($filters->search !== null) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhere('furniture_type', 'ILIKE', "%{$filters->search}%");
            });
        }
        if ($filters->furnitureType !== null) {
            $query->where('furniture_type', $filters->furnitureType);
        }

        return $query->orderBy('sort_order')->orderBy('id')->paginate($perPage, ['*'], 'page', $filters->page)->withQueryString();
    }

    public function findActiveBySlug(string $slug): ?Furniture
    {
        return Furniture::query()
            ->active()
            ->where('slug', $slug)
            ->first();
    }
}

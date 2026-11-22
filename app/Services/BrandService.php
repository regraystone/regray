<?php

namespace App\Services;

use App\Data\BrandProductFilterData;
use App\Http\Resources\ShowcaseCardResource;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandService
{
    public function __construct(
        public readonly BrandRepositoryInterface $brandRepository,
    ) {}

    /** @return Collection<int, Brand> */
    public function listActive(): Collection
    {
        return $this->brandRepository->active();
    }

    /**
     * What a brand offers, counted from its active products: how many stones
     * and pieces of furniture it has, and each stone type its stones come in,
     * then each furniture type, in catalog order. A stone of two types counts
     * in both, as the collection's type filter finds it under both. A stone
     * type's photo is its own thumbnail (set on its Products page); a
     * furniture type has none, so its photo is the brand's first piece of
     * that type. `value` is what the collection's filter takes for it.
     *
     * @return array{stones: int, furniture: int, types: list<array{collection: 'stones'|'furniture', value: string, name: string, count: int, thumbnail: string|null}>}
     */
    public function offerings(Brand $brand): array
    {
        $products = $this->brandRepository->activeProductsWithTypes($brand);

        /** @var array<int, array{type: StoneType, count: int, thumbnail: string}> $stoneTypes */
        $stoneTypes = [];

        foreach ($products['stones'] as $stone) {
            foreach ($stone->types as $type) {
                $stoneTypes[$type->id] ??= ['type' => $type, 'count' => 0, 'thumbnail' => $type->thumbnail_path];
                $stoneTypes[$type->id]['count']++;
            }
        }

        /** @var array<int, array{type: FurnitureType, count: int, thumbnail: string|null}> $furnitureTypes */
        $furnitureTypes = [];

        foreach ($products['furniture'] as $piece) {
            $furnitureTypes[$piece->furniture_type_id] ??= ['type' => $piece->furnitureType, 'count' => 0, 'thumbnail' => null];
            $furnitureTypes[$piece->furniture_type_id]['count']++;
            $furnitureTypes[$piece->furniture_type_id]['thumbnail'] ??= $piece->thumbnail_path;
        }

        $inCatalogOrder = fn (array $left, array $right): int => [$left['type']->sort_order, $left['type']->name] <=> [$right['type']->sort_order, $right['type']->name];
        usort($stoneTypes, $inCatalogOrder);
        usort($furnitureTypes, $inCatalogOrder);

        $types = [];

        foreach ($stoneTypes as $offer) {
            $types[] = [
                'collection' => 'stones',
                'value' => $offer['type']->slug,
                'name' => $offer['type']->name,
                'count' => $offer['count'],
                'thumbnail' => ShowcaseCardResource::resolveThumbnailUrl($offer['thumbnail']),
            ];
        }

        foreach ($furnitureTypes as $offer) {
            $types[] = [
                'collection' => 'furniture',
                'value' => $offer['type']->name,
                'name' => $offer['type']->name,
                'count' => $offer['count'],
                'thumbnail' => $offer['thumbnail'] === null ? null : ShowcaseCardResource::resolveThumbnailUrl($offer['thumbnail']),
            ];
        }

        return [
            'stones' => $products['stones']->count(),
            'furniture' => $products['furniture']->count(),
            'types' => $types,
        ];
    }

    /**
     * The other active brands, in their home page order.
     *
     * @return Collection<int, Brand>
     */
    public function othersThan(Brand $brand): Collection
    {
        return $this->brandRepository->active()
            ->reject(fn (Brand $other): bool => $other->is($brand))
            ->values();
    }

    /**
     * @return array{products: LengthAwarePaginator<int, Stone|Furniture>, hasProducts: bool, filters: array{collection: string, search: string|null, type: string|null, size: string|null, thickness: string|null, furnitureType: string|null, perPage: int}}
     */
    public function listProducts(Brand $brand, BrandProductFilterData $filters): array
    {
        return [
            'products' => $this->brandRepository->paginateProducts($brand, $this->withEscapedSearch($filters)),
            'hasProducts' => $this->brandRepository->hasActiveProducts($brand),
            'filters' => [
                'collection' => $filters->collection,
                'search' => $filters->search,
                'type' => $filters->type,
                'size' => $filters->size,
                'thickness' => $filters->thickness,
                'furnitureType' => $filters->furnitureType,
                'perPage' => $filters->perPage,
            ],
        ];
    }

    /** A `%` or `_` someone typed is searched for, not treated as a wildcard. */
    private function withEscapedSearch(BrandProductFilterData $filters): BrandProductFilterData
    {
        return new BrandProductFilterData(
            $filters->collection,
            $filters->search === null ? null : str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters->search),
            $filters->type,
            $filters->size,
            $filters->thickness,
            $filters->furnitureType,
            $filters->page,
            $filters->perPage,
        );
    }
}

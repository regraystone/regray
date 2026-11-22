<?php

namespace App\Repositories\Eloquent;

use App\Data\Product\ProductListFilters;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use App\Repositories\Contracts\ProductAdminRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Stones and furniture are two tables, so the page lists them as one through
 * a UNION of each table's kind, id and name, filtered the same way, sorted
 * and paged as one list. The page's rows are then read as models, each with
 * what its row and Edit form show, in the list's order.
 */
class ProductAdminRepository implements ProductAdminRepositoryInterface
{
    public function paginate(ProductListFilters $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $tables = array_filter([
            'stone' => $filters->kind === null || $filters->kind === 'stone' ? $this->rows('stones', 'stone', $filters) : null,
            'furniture' => $filters->kind === null || $filters->kind === 'furniture' ? $this->rows('furniture', 'furniture', $filters) : null,
        ]);
        $union = array_shift($tables);

        foreach ($tables as $rows) {
            $union->unionAll($rows);
        }

        /** @var LengthAwarePaginator<int, object{kind: string, id: int, name: string}> $paginator */
        $paginator = DB::query()->fromSub($union, 'products')
            ->orderByRaw('lower(name)')
            ->orderBy('kind', 'desc')
            ->orderBy('id')
            ->paginate($perPage, ['kind', 'id', 'name'], 'page', $page);

        $ids = fn (string $kind): array => $paginator->getCollection()->where('kind', $kind)->pluck('id')->all();
        $stones = Stone::query()->whereKey($ids('stone'))->with([
            'brand:id,slug,name',
            'type:id,name',
            'types:stone_types.id,name',
            'variants',
            'images' => fn (Relation $images) => $images->getQuery()->orderBy('sort_order'),
        ])->get()->keyBy('id');
        $furniture = Furniture::query()->whereKey($ids('furniture'))->with([
            'brand:id,slug,name',
            'furnitureType:id,name',
            'images' => fn (Relation $images) => $images->getQuery()->orderBy('sort_order'),
        ])->get()->keyBy('id');

        /** @var LengthAwarePaginator<int, Stone|Furniture> $products */
        $products = $paginator->setCollection($paginator->getCollection()->map(
            fn (object $row): Stone|Furniture => $row->kind === 'stone' ? $stones[$row->id] : $furniture[$row->id],
        ));

        return $products;
    }

    public function formOptions(): array
    {
        return [
            'brands' => array_values(Brand::query()->notArchived()->orderBy('name')->get(['id', 'slug', 'name'])
                ->map(fn (Brand $brand): array => ['id' => $brand->id, 'slug' => $brand->slug, 'name' => $brand->name])->all()),
            'stoneTypes' => array_values(StoneType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (StoneType $type): array => ['id' => $type->id, 'name' => $type->name])->all()),
            'furnitureTypes' => array_values(FurnitureType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (FurnitureType $type): array => ['id' => $type->id, 'name' => $type->name])->all()),
            'sizes' => array_values(Size::query()->get()
                ->sortBy([
                    fn (Size $left, Size $right): int => $left->unit->position() <=> $right->unit->position(),
                    fn (Size $left, Size $right): int => (float) $left->width <=> (float) $right->width,
                    fn (Size $left, Size $right): int => (float) $left->length <=> (float) $right->length,
                ])
                ->map(fn (Size $size): array => ['id' => $size->id, 'label' => $size->label])->values()->all()),
            'thicknesses' => array_values(Thickness::query()->get()
                ->sortBy([
                    fn (Thickness $left, Thickness $right): int => $left->unit->position() <=> $right->unit->position(),
                    fn (Thickness $left, Thickness $right): int => (float) $left->value <=> (float) $right->value,
                ])
                ->map(fn (Thickness $thickness): array => ['id' => $thickness->id, 'label' => $thickness->label])->values()->all()),
        ];
    }

    /** One table's rows for the list: not archived, of a brand not archived, filtered. */
    private function rows(string $table, string $kind, ProductListFilters $filters): Builder
    {
        return DB::table($table)
            ->selectRaw('? AS kind, id, name', [$kind])
            ->whereNull('deleted_at')
            ->whereIn('brand_id', Brand::query()->notArchived()->select('id'))
            ->when($filters->escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$filters->escapedSearch}%"))
            ->when($filters->brandSlug !== null, fn (Builder $query) => $query->whereIn('brand_id', Brand::query()->where('slug', $filters->brandSlug)->select('id')))
            ->when($filters->active !== null, fn (Builder $query) => $query->where('is_active', $filters->active));
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Data\BrandProductFilterData;
use App\Http\Requests\Brand\BrandCollectionRequest;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

class BrandRepository implements BrandRepositoryInterface
{
    /** @return Collection<int, Brand> */
    public function active(): Collection
    {
        return Brand::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'tagline', 'website_url', 'logo_path', 'thumbnail_path', 'description']);
    }

    /**
     * Stones and furniture live in two tables, so the page of ids is taken from
     * a union of the two and the models are loaded after. Every collection goes
     * through it so there is one search and one pagination. All Categories
     * lists the stones first and then the furniture, each by name; a single
     * kind keeps its merchandised order.
     *
     * @return LengthAwarePaginator<int, Stone|Furniture>
     */
    public function paginateProducts(Brand $brand, BrandProductFilterData $filters): LengthAwarePaginator
    {
        $rows = match ($filters->collection) {
            BrandCollectionRequest::COLLECTION_STONES => $this->stoneRows($brand, $filters),
            BrandCollectionRequest::COLLECTION_FURNITURE => $this->furnitureRows($brand, $filters),
            default => $this->stoneRows($brand, $filters)->unionAll($this->furnitureRows($brand, $filters)),
        };

        $query = DB::query()->fromSub($rows, 'products');

        if ($filters->collection === BrandCollectionRequest::COLLECTION_ALL) {
            $query->orderByRaw("CASE kind WHEN 'stone' THEN 0 ELSE 1 END")->orderByRaw('lower(name)');
        } else {
            $query->orderBy('sort_order');
        }

        /** @var LengthAwarePaginator<int, stdClass> $page */
        $page = $query->orderBy('id')
            ->paginate($filters->perPage, ['id', 'kind'], 'page', $filters->page)
            ->withQueryString();

        return $this->withModels($brand, $page);
    }

    public function hasActiveProducts(Brand $brand): bool
    {
        return $brand->stones()->active()->exists() || $brand->furniture()->active()->exists();
    }

    private function stoneRows(Brand $brand, BrandProductFilterData $filters): QueryBuilder
    {
        $query = Stone::query()
            ->active()
            ->where('brand_id', $brand->id)
            ->select(['id', 'name', 'sort_order'])
            ->selectRaw("'stone' AS kind");

        if ($filters->search !== null) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhereHas('categories', fn (Builder $category) => $category->where('name', 'ILIKE', "%{$filters->search}%"));
            });
        }
        if ($filters->category !== null) {
            $query->whereHas('categories', fn (Builder $category) => $category->where('slug', $filters->category)->where('is_active', true));
        }
        if ($filters->size !== null) {
            $query->where('size', $filters->size);
        }
        if ($filters->thickness !== null) {
            $query->where('thickness_mm', (int) $filters->thickness);
        }

        return $query->toBase();
    }

    private function furnitureRows(Brand $brand, BrandProductFilterData $filters): QueryBuilder
    {
        $query = Furniture::query()
            ->active()
            ->where('brand_id', $brand->id)
            ->select(['id', 'name', 'sort_order'])
            ->selectRaw("'furniture' AS kind");

        if ($filters->search !== null) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhereHas('furnitureType', fn (Builder $type) => $type->where('name', 'ILIKE', "%{$filters->search}%"));
            });
        }
        if ($filters->furnitureType !== null) {
            $query->whereHas('furnitureType', fn (Builder $type) => $type->where('name', $filters->furnitureType));
        }

        return $query->toBase();
    }

    /**
     * @param  LengthAwarePaginator<int, stdClass>  $page
     * @return LengthAwarePaginator<int, Stone|Furniture>
     */
    private function withModels(Brand $brand, LengthAwarePaginator $page): LengthAwarePaginator
    {
        $rows = $page->getCollection();
        $idsOf = fn (string $kind): array => $rows->where('kind', $kind)->pluck('id')->all();

        $stones = Stone::query()->with('category:id,slug,name')->findMany($idsOf('stone'))->keyBy('id');
        $furniture = Furniture::query()->with('furnitureType:id,slug,name')->findMany($idsOf('furniture'))->keyBy('id');

        /** @var LengthAwarePaginator<int, Stone|Furniture> $page */
        $page->setCollection($rows->map(
            fn (stdClass $row): Stone|Furniture => ($row->kind === 'stone' ? $stones[$row->id] : $furniture[$row->id])
                ->setRelation('brand', $brand)
        ));

        return $page;
    }
}

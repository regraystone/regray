<?php

namespace App\Repositories\Eloquent;

use App\Models\Showcase;
use App\Models\ShowcaseCategory;
use App\Models\Stone;
use App\Repositories\Contracts\ShowcaseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class ShowcaseRepository implements ShowcaseRepositoryInterface
{
    public function latestPublished(int $limit): Collection
    {
        return Showcase::query()
            ->published()
            ->orderByDesc('completed_year')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'slug', 'name', 'location', 'completed_year', 'thumbnail_path']);
    }

    /**
     * Load the profile only after permission has been settled, rather than in route binding.
     * Related keys are retained so Eloquent can match the constrained eager loads.
     */
    public function findPublishedInStone(string $stoneSlug, string $showcaseSlug, bool $withArchived = false): ?Showcase
    {
        $showcase = Showcase::query()
            ->when($withArchived, fn (Builder $showcases) => $showcases->withTrashed())
            ->published()
            ->where('slug', $showcaseSlug)
            ->whereHas('stones', fn (Builder $stones) => $stones->active()->where('slug', $stoneSlug))
            ->with([
                'images' => fn (Relation $images) => $images->getQuery()
                    ->select(['id', 'showcase_id', 'path', 'original_name'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_type_id', 'slug', 'name', 'thumbnail_path'])
                    ->with('type:id,slug,name')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()
                    ->select(['showcase_categories.id', 'slug', 'name'])
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->first();

        if ($showcase !== null) {
            $showcase->setRelation('parentStone', $showcase->stones->firstWhere('slug', $stoneSlug));
        }

        return $showcase;
    }

    public function findPublishedBySlug(string $slug): ?Showcase
    {
        return Showcase::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'images' => fn (Relation $images) => $images->getQuery()
                    ->select(['id', 'showcase_id', 'path', 'original_name'])
                    ->orderBy('sort_order'),
                'stones' => fn (Relation $stones) => $stones->getQuery()
                    ->select(['stones.id', 'stone_type_id', 'slug', 'name', 'thumbnail_path'])
                    ->with('type:id,slug,name')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()
                    ->select(['showcase_categories.id', 'slug', 'name'])
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->first();
    }

    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator
    {
        return Showcase::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->with([
                'stones' => fn (Relation $stones) => $stones->getQuery()->select(['stones.id', 'stones.name'])->orderBy('stones.name'),
                'categories' => fn (Relation $categories) => $categories->getQuery()->select(['showcase_categories.id', 'showcase_categories.name'])->orderBy('showcase_categories.sort_order'),
                'gallery',
            ])
            ->orderByDesc('completed_year')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, page: $page);
    }

    public function adminOptions(): array
    {
        return [
            'stones' => array_values(Stone::query()->active()->with('brand:id,name')->orderBy('name')->orderBy('id')->get(['id', 'brand_id', 'name'])
                ->map(fn (Stone $stone): array => ['id' => $stone->id, 'name' => $stone->name, 'brand' => $stone->brand->name])->all()),
            'categories' => array_values(ShowcaseCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (ShowcaseCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all()),
        ];
    }
}

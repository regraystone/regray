<?php

namespace App\Repositories\Eloquent;

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class StoneRepository implements StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone
    {
        return $this->profileQuery()->where('slug', $slug)->first();
    }

    /** @return LengthAwarePaginator<int, Stone> */
    public function paginate(StoneFilterData $filters, int $perPage): LengthAwarePaginator
    {
        $query = Stone::query()->active()->with('category:id,slug,name');

        if ($filters->search !== null) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'ILIKE', "%{$filters->search}%"));
            });
        }
        if ($filters->category !== null) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters->category));
        }
        if ($filters->size !== null) {
            $query->where('size', $filters->size);
        }
        if ($filters->thickness === '18-20') {
            $query->whereBetween('thickness_mm', [18, 20]);
        } elseif ($filters->thickness === '21-30') {
            $query->whereBetween('thickness_mm', [21, 30]);
        } elseif ($filters->thickness === '31-plus') {
            $query->where('thickness_mm', '>=', 31);
        }

        return $query->orderBy('sort_order')->orderBy('id')->paginate($perPage, ['*'], 'page', $filters->page)->withQueryString();
    }

    /**
     * @return Collection<int, Stone>
     */
    public function recommended(): Collection
    {
        return Stone::query()
            ->active()
            ->where('recommended', true)
            ->with('category:id,slug,name')
            ->select(['stones.id', 'stones.stone_category_id', 'stones.slug', 'stones.name', 'stones.thumbnail_path', 'stones.recommended'])
            ->orderBy('stones.sort_order')
            ->orderBy('stones.name')
            ->get();
    }

    public function findActiveSummaryBySlug(string $slug): ?Stone
    {
        return Stone::query()
            ->active()
            ->where('slug', $slug)
            ->select(['id', 'slug'])
            ->first();
    }

    /**
     * Both doors expose the same profile. Keep their eager loads together and retain matching keys.
     * The model already orders the fact lists by pivot; advisory finishes belong only to inquiries.
     *
     * @return Builder<Stone>
     */
    private function profileQuery(): Builder
    {
        return Stone::query()->active()->with([
            'images' => fn (Relation $images) => $images->getQuery()
                ->select(['id', 'stone_id', 'alt_text', 'path'])
                ->orderBy('sort_order'),
            'category',
            'category.applications' => fn (Relation $applications) => $applications->getQuery()
                ->select(['applications.id', 'name']),
            'category.careTips' => fn (Relation $careTips) => $careTips->getQuery()
                ->select(['care_tips.id', 'tip']),
            'category.finishes' => fn (Relation $finishes) => $finishes->getQuery()
                ->select(['finishes.id', 'name'])
                ->where('is_active', true)
                ->where('is_advisory', false),
            'projects' => fn (Relation $projects) => $projects->getQuery()
                ->select(['projects.id', 'slug', 'name', 'thumbnail_path'])
                ->where('is_published', true)
                ->orderByDesc('completed_year')
                ->orderBy('name'),
        ]);
    }

    /**
     * @return array{categories: SupportCollection<int, array{value: string, label: string}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $categories = StoneCategory::query()
            ->active()
            ->whereHas('stones', fn (Builder $stones) => $stones->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (StoneCategory $stone): array => [
                'value' => $stone->slug,
                'label' => $stone->name,
            ]);

        $sizes = Stone::query()->active()->distinct()->pluck('size')
            ->sort(function (string $left, string $right): int {
                [$leftWidth, $leftLength] = array_map('intval', explode('x', $left));
                [$rightWidth, $rightLength] = array_map('intval', explode('x', $right));

                return ($leftWidth <=> $rightWidth) ?: ($leftLength <=> $rightLength);
            })->values()->map(fn (string $size): array => ['value' => $size, 'label' => $size]);
        $thicknesses = Stone::query()->active()->distinct()->pluck('thickness_mm');
        $buckets = collect([
            ['value' => '18-20', 'label' => '18-20 mm', 'min' => 18, 'max' => 20],
            ['value' => '21-30', 'label' => '21-30 mm', 'min' => 21, 'max' => 30],
            ['value' => '31-plus', 'label' => '31 mm and up', 'min' => 31, 'max' => PHP_INT_MAX],
        ])->filter(fn (array $bucket): bool => $thicknesses->contains(fn (int $thickness): bool => $thickness >= $bucket['min'] && $thickness <= $bucket['max']))
            ->values()->map(fn (array $bucket): array => ['value' => $bucket['value'], 'label' => $bucket['label']]);

        return ['categories' => $categories, 'sizes' => $sizes, 'thicknesses' => $buckets];
    }
}

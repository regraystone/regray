<?php

namespace App\Repositories\Eloquent;

use App\Data\StoneFilterData;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryStone;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\StoneSize;
use App\Models\StoneThickness;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

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
                    ->orWhereHas('categories', fn (Builder $category) => $category->where('name', 'ILIKE', "%{$filters->search}%"));
            });
        }
        if ($filters->category !== null) {
            $query->whereHas('categories', fn (Builder $category) => $category->where('slug', $filters->category)->where('is_active', true));
        }
        if ($filters->projectCategory !== null) {
            $query->whereHas('projectCategories', fn (Builder $projectCategory) => $projectCategory->where('slug', $filters->projectCategory)->where('is_active', true));
        }
        if ($filters->size !== null) {
            $query->whereHas('sizes', fn (Builder $size) => $size->where('name', $filters->size)->where('is_active', true));
        }
        if ($filters->thickness !== null) {
            $query->whereHas('thicknesses', fn (Builder $thickness) => $thickness->where('slug', $filters->thickness)->where('is_active', true));
        }

        return $query->orderBy('sort_order')->orderBy('id')->paginate($perPage, ['*'], 'page', $filters->page)->withQueryString();
    }

    /**
     * Ranked with RANK() so stones level on count share a place, and every
     * stone placed within `$count` is kept.
     *
     * @return list<int>
     */
    public function bestSellerIds(int $count): array
    {
        $ranked = InquiryStone::query()
            ->whereIn('inquiry_id', Inquiry::query()->whereDisplayStatus(InquiryStatus::Closed)->select('id'))
            ->whereIn('stone_id', Stone::query()->active()->select('id'))
            ->groupBy('stone_id')
            ->select('stone_id')
            ->selectRaw('RANK() OVER (ORDER BY COUNT(DISTINCT inquiry_id) DESC) AS position');

        return array_values(DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', '<=', $count)
            ->pluck('stone_id')
            ->map(fn (mixed $stoneId): int => (int) $stoneId)
            ->all());
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (StoneCategory $stone): array => [
                'value' => $stone->slug,
                'label' => $stone->name,
            ]);

        $sizes = StoneSize::query()->where('is_active', true)->pluck('name')
            ->sort(function (string $left, string $right): int {
                [$leftWidth, $leftLength] = array_map('intval', explode('x', $left));
                [$rightWidth, $rightLength] = array_map('intval', explode('x', $right));

                return ($leftWidth <=> $rightWidth) ?: ($leftLength <=> $rightLength);
            })->values()->map(fn (string $size): array => ['value' => $size, 'label' => $size]);
        $thicknesses = StoneThickness::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get(['slug', 'name'])
            ->map(fn (StoneThickness $thickness): array => ['value' => $thickness->slug, 'label' => $thickness->name]);

        return ['categories' => $categories, 'sizes' => $sizes, 'thicknesses' => $thicknesses];
    }
}

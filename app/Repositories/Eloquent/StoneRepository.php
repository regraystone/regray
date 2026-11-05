<?php

namespace App\Repositories\Eloquent;

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
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class StoneRepository implements StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone
    {
        return $this->profileQuery()->where('slug', $slug)->first();
    }

    /**
     * Ranked with RANK() so categories level on count share a place, and every
     * category placed within `$count` is kept.
     *
     * @return list<int>
     */
    public function bestSellerCategoryIds(int $count): array
    {
        $ranked = InquiryStone::query()
            ->joinSub(Stone::query()->active()->select(['id', 'stone_category_id']), 'active_stones', 'active_stones.id', '=', 'inquiry_stones.stone_id')
            ->whereIn('inquiry_stones.inquiry_id', Inquiry::query()->whereDisplayStatus(InquiryStatus::Closed)->select('id'))
            ->whereIn('active_stones.stone_category_id', StoneCategory::query()->active()->select('id'))
            ->groupBy('active_stones.stone_category_id')
            ->select('active_stones.stone_category_id')
            ->selectRaw('RANK() OVER (ORDER BY COUNT(DISTINCT inquiry_stones.inquiry_id) DESC) AS position');

        return array_values(DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', '<=', $count)
            ->pluck('stone_category_id')
            ->map(fn (mixed $categoryId): int => (int) $categoryId)
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
            'brand:id,slug,name',
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
        ]);
    }

    /**
     * @return array{categories: SupportCollection<int, array{value: string, label: string, bestSeller: bool}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $categories = StoneCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (StoneCategory $stone): array => [
                'value' => $stone->slug,
                'label' => $stone->name,
                'bestSeller' => $stone->is_best_seller,
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

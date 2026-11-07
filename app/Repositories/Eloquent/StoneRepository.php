<?php

namespace App\Repositories\Eloquent;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryStone;
use App\Models\Stone;
use App\Models\StoneType;
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
     * Ranked with RANK() so types level on count share a place, and every
     * type placed within `$count` is kept.
     *
     * @return list<int>
     */
    public function bestSellerTypeIds(int $count): array
    {
        $ranked = InquiryStone::query()
            ->joinSub(Stone::query()->active()->select(['id', 'stone_type_id']), 'active_stones', 'active_stones.id', '=', 'inquiry_stones.stone_id')
            ->whereIn('inquiry_stones.inquiry_id', Inquiry::query()->whereDisplayStatus(InquiryStatus::Closed)->select('id'))
            ->whereIn('active_stones.stone_type_id', StoneType::query()->active()->select('id'))
            ->groupBy('active_stones.stone_type_id')
            ->select('active_stones.stone_type_id')
            ->selectRaw('RANK() OVER (ORDER BY COUNT(DISTINCT inquiry_stones.inquiry_id) DESC) AS position');

        return array_values(DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', '<=', $count)
            ->pluck('stone_type_id')
            ->map(fn (mixed $typeId): int => (int) $typeId)
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
            'type',
            'type.applications' => fn (Relation $applications) => $applications->getQuery()
                ->select(['applications.id', 'name']),
            'type.careTips' => fn (Relation $careTips) => $careTips->getQuery()
                ->select(['care_tips.id', 'tip']),
            'type.finishes' => fn (Relation $finishes) => $finishes->getQuery()
                ->select(['finishes.id', 'name'])
                ->where('is_active', true)
                ->where('is_advisory', false),
        ]);
    }

    /**
     * @return array{types: SupportCollection<int, array{value: string, label: string, bestSeller: bool}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $types = StoneType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (StoneType $stone): array => [
                'value' => $stone->slug,
                'label' => $stone->name,
                'bestSeller' => $stone->is_best_seller,
            ]);

        $sizes = Stone::query()->active()->distinct()->pluck('size')
            ->sort(function (string $left, string $right): int {
                [$leftWidth, $leftLength] = array_map('intval', explode('x', $left));
                [$rightWidth, $rightLength] = array_map('intval', explode('x', $right));

                return ($leftWidth <=> $rightWidth) ?: ($leftLength <=> $rightLength);
            })->values()->map(fn (string $size): array => ['value' => $size, 'label' => $size]);
        $thicknesses = Stone::query()->active()->distinct()->orderBy('thickness_mm')->pluck('thickness_mm')
            ->map(fn (int $millimetres): array => ['value' => (string) $millimetres, 'label' => "{$millimetres} mm"]);

        return ['types' => $types, 'sizes' => $sizes, 'thicknesses' => $thicknesses];
    }
}

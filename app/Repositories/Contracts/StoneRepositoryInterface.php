<?php

namespace App\Repositories\Contracts;

use App\Models\Showcase;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    /** Id and slug only, for saving a stone without the heavy profile query. */
    public function findActiveSummaryBySlug(string $slug): ?Stone;

    /**
     * The ids of the active stone types ranked in the top `$count` by how
     * many completed inquiries their active stones are in, ties at the last
     * place included.
     *
     * @return list<int>
     */
    public function bestSellerTypeIds(int $count): array;

    /**
     * @return array{types: SupportCollection<int, array{value: string, label: string, bestSeller: bool}>, sizes: SupportCollection<int, array{value: string, label: string}>, thicknesses: SupportCollection<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array;

    /**
     * The active stones of one brand and type in their catalog order, for
     * stepping from one stone to the next.
     *
     * @return Collection<int, Stone>
     */
    public function activeOfBrandAndType(int $brandId, int $typeId): Collection;

    /**
     * Up to `$limit` other active stones of the same brand and type, in
     * catalog order, loaded for their cards.
     *
     * @return Collection<int, Stone>
     */
    public function relatedTo(Stone $stone, int $limit): Collection;

    /**
     * The published showcases the stone was used in, newest first.
     *
     * @return Collection<int, Showcase>
     */
    public function publishedShowcasesUsing(Stone $stone, int $limit): Collection;
}

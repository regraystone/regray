<?php

namespace App\Repositories\Contracts;

use App\Data\Product\ProductListFilters;
use App\Models\Furniture;
use App\Models\Stone;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductAdminRepositoryInterface
{
    /**
     * The Products page's rows: stones and furniture together, but the
     * archived and those of an archived brand, by name, a page at a time,
     * each loaded with what its row and its Edit form read.
     *
     * @return LengthAwarePaginator<int, Stone|Furniture>
     */
    public function paginate(ProductListFilters $filters, int $perPage, int $page): LengthAwarePaginator;

    /**
     * What the Add and Edit forms choose from: the brands not archived, the
     * active stone and furniture types, every size and thickness not
     * archived, metric first, then smallest first, and the shapes and base
     * designs a table of each type may be given (by type id, then column).
     *
     * @return array{brands: list<array{id: int, slug: string, name: string}>, stoneTypes: list<array{id: int, name: string}>, furnitureTypes: list<array{id: int, name: string}>, sizes: list<array{id: int, label: string}>, thicknesses: list<array{id: int, label: string}>, tableChoices: array<int, array<string, list<array{id: int, name: string}>>>}
     */
    public function formOptions(): array;
}

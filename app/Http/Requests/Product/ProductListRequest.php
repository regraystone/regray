<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\DataTableRequest;

/**
 * The Products page's query: the DataTable's search and page, and its three
 * filters. `kind` is `stone`, `furniture` or `all`; `brand` a brand's slug,
 * or `all`; `status` is `active`, `inactive` or `all`. A value it doesn't
 * know is `all`, as a DataTable's bad value falls back to its default.
 */
class ProductListRequest extends DataTableRequest
{
    public const KINDS = ['stone', 'furniture'];

    public const STATUSES = ['active', 'inactive'];

    public function kind(): ?string
    {
        return self::pick($this->query('kind'), self::KINDS);
    }

    /** The brand's slug, or null for every brand. */
    public function brand(): ?string
    {
        $brand = $this->query('brand');

        return is_string($brand) && $brand !== '' && $brand !== 'all' && strlen($brand) <= 100 ? $brand : null;
    }

    /** True for the active, false for the inactive, null for both. */
    public function active(): ?bool
    {
        $status = self::pick($this->query('status'), self::STATUSES);

        return $status === null ? null : $status === 'active';
    }

    /**
     * The filters as the page holds them.
     *
     * @return array{search: string, page: int, perPage: int, kind: string, brand: string, status: string}
     */
    public function productFilters(): array
    {
        $active = $this->active();

        return [
            ...$this->filters(),
            'kind' => $this->kind() ?? 'all',
            'brand' => $this->brand() ?? 'all',
            'status' => $active === null ? 'all' : ($active ? 'active' : 'inactive'),
        ];
    }

    /** @param list<string> $allowed */
    private static function pick(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}

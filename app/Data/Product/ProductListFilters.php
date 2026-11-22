<?php

namespace App\Data\Product;

/** What the Products page lists: its search (escaped for ILIKE) and filters, each null for all. */
final readonly class ProductListFilters
{
    public function __construct(
        public ?string $escapedSearch,
        public ?string $kind,
        public ?string $brandSlug,
        public ?bool $active,
    ) {}
}

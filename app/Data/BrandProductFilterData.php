<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use App\Http\Requests\Brand\BrandCollectionRequest;

final readonly class BrandProductFilterData
{
    public function __construct(
        public string $collection,
        public ?string $search,
        public ?string $category,
        public ?string $size,
        public ?string $thickness,
        public ?string $furnitureType,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(BrandCollectionRequest $request): self
    {
        return new self(
            collection: $request->validated('collection') ?? BrandCollectionRequest::COLLECTION_ALL,
            search: $request->validated('search'),
            category: $request->validated('category'),
            size: $request->validated('size'),
            thickness: $request->validated('thickness'),
            furnitureType: $request->validated('furniture_type'),
            page: (int) ($request->validated('page') ?? 1),
            perPage: (HomeGridPageSize::tryFrom((int) $request->cookie(HomeGridPageSize::COOKIE)) ?? HomeGridPageSize::DEFAULT)->value,
        );
    }
}

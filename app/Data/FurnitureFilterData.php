<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use App\Http\Requests\Home\FurnitureIndexRequest;

final readonly class FurnitureFilterData
{
    public function __construct(
        public ?string $search,
        public ?string $furnitureType,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(FurnitureIndexRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            furnitureType: $request->validated('furniture_type'),
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? HomeGridPageSize::DEFAULT->value),
        );
    }
}

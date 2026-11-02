<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use App\Http\Requests\Home\StoneIndexRequest;

final readonly class StoneFilterData
{
    public function __construct(
        public ?string $search,
        public ?string $category,
        public ?string $size,
        public ?string $thickness,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(StoneIndexRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            category: $request->validated('category'),
            size: $request->validated('size'),
            thickness: $request->validated('thickness'),
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? HomeGridPageSize::DEFAULT->value),
        );
    }
}

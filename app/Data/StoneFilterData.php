<?php

namespace App\Data;

use App\Http\Requests\Home\StoneIndexRequest;

final readonly class StoneFilterData
{
    public function __construct(
        public ?string $search,
        public ?string $category,
        public ?string $size,
        public ?string $thickness,
        public int $page,
    ) {}

    public static function fromRequest(StoneIndexRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            category: $request->validated('category'),
            size: $request->validated('size'),
            thickness: $request->validated('thickness'),
            page: (int) ($request->validated('page') ?? 1),
        );
    }
}

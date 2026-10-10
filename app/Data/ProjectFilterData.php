<?php

namespace App\Data;

use App\Http\Requests\Home\ProjectIndexRequest;

final readonly class ProjectFilterData
{
    public function __construct(
        public ?string $search,
        public ?string $stone,
        public ?string $category,
        public int $page,
    ) {}

    public static function fromRequest(ProjectIndexRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            stone: $request->validated('stone'),
            category: $request->validated('category'),
            page: (int) ($request->validated('page') ?? 1),
        );
    }
}

<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use App\Http\Requests\Home\ProjectIndexRequest;

final readonly class ProjectFilterData
{
    public function __construct(
        public ?string $search,
        public ?string $projectCategory,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(ProjectIndexRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            projectCategory: $request->validated('project_category'),
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? HomeGridPageSize::DEFAULT->value),
        );
    }
}

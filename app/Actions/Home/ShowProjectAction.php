<?php

namespace App\Actions\Home;

use App\Models\Project;
use App\Services\ProjectService;

class ShowProjectAction
{
    public function __construct(
        public readonly ProjectService $projectService,
    ) {}

    public function execute(string $slug): Project
    {
        return $this->projectService->getPublishedProject($slug);
    }
}

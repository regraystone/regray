<?php

namespace App\Actions\Home;

use App\Models\Project;
use App\Services\ProjectService;

class ShowProjectAction
{
    public function __construct(
        public readonly ProjectService $projectService,
    ) {}

    /** `$withArchived` finds an archived project too: only the saved stone's projects keep it. */
    public function execute(string $stoneSlug, string $projectSlug, bool $withArchived = false): Project
    {
        return $this->projectService->getPublishedProject($stoneSlug, $projectSlug, $withArchived);
    }
}

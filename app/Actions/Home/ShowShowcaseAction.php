<?php

namespace App\Actions\Home;

use App\Models\Showcase;
use App\Services\ShowcaseService;

class ShowShowcaseAction
{
    public function __construct(
        public readonly ShowcaseService $showcaseService,
    ) {}

    /** `$withArchived` finds an archived showcase too: only the saved stone's showcases keep it. */
    public function execute(string $stoneSlug, string $showcaseSlug, bool $withArchived = false): Showcase
    {
        return $this->showcaseService->getPublishedShowcase($stoneSlug, $showcaseSlug, $withArchived);
    }
}

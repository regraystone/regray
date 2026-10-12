<?php

namespace App\Actions\Stone;

use App\Models\Stone;
use App\Services\StoneService;

class ShowStoneAction
{
    public function __construct(
        public readonly StoneService $stoneService,
    ) {}

    public function execute(string $stoneSlug, ?string $projectSlug = null): Stone
    {
        return $this->stoneService->getActiveStone($stoneSlug, $projectSlug);
    }
}

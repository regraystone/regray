<?php

namespace App\Actions\Home;

use App\Models\Brand;
use App\Models\Project;
use App\Models\Stone;

/**
 * The figures the About page quotes, counted from the catalog itself so they
 * never drift from it: the active brands, the active stones and the
 * published projects.
 */
class CompanyFactsAction
{
    /**
     * @return array{brands: int, stones: int, projects: int}
     */
    public function execute(): array
    {
        return [
            'brands' => Brand::query()->active()->count(),
            'stones' => Stone::query()->active()->count(),
            'projects' => Project::query()->published()->count(),
        ];
    }
}

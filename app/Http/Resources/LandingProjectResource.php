<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A finished project as the landing page shows it: its photo, name, where it
 * is and when it was completed. It links to the project's public profile at
 * `/{slug}`.
 *
 * @mixin Project
 */
class LandingProjectResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, location: string, completedYear: int, thumbnail: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'location' => $this->location,
            'completedYear' => $this->completed_year,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
        ];
    }
}

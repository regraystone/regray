<?php

namespace App\Http\Resources;

use App\Models\Showcase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A finished showcase as the landing page shows it: its photo, name, where it
 * is and when it was completed. It links to the showcase's public profile at
 * `/{slug}`.
 *
 * @mixin Showcase
 */
class LandingShowcaseResource extends JsonResource
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
            'thumbnail' => ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
        ];
    }
}

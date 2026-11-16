<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand as the landing page introduces it: its photo with the logo laid
 * over it, then its name and what it makes (the description, or the tagline
 * when it has none). It links to the brand's public page.
 *
 * @mixin Brand
 */
class LandingBrandResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, description: string|null, logo: string, thumbnail: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description ?? $this->tagline,
            'logo' => ProjectCardResource::resolveThumbnailUrl($this->logo_path),
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
        ];
    }
}

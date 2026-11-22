<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand tile on the home page.
 *
 * @mixin Brand
 */
class BrandCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, tagline: string|null, logo: string, thumbnail: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'logo' => ShowcaseCardResource::resolveThumbnailUrl($this->logo_path),
            'thumbnail' => $this->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand's page: who it is, its facts, what it makes and its social links
 * (set on the admin's Brands page). Its own website stays on the admin's page
 * and is not shown here: clients buy through Regray.
 *
 * Expects Brand::DETAILS loaded (BrandController::show()).
 *
 * @mixin Brand
 */
class BrandProfileResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, tagline: string|null, description: string|null, logo: string, thumbnail: string|null, facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'logo' => ShowcaseCardResource::resolveThumbnailUrl($this->logo_path),
            'thumbnail' => $this->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
            ...$this->details(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Furniture */
class FurnitureCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string|null, projectType: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'projectType' => ['slug' => $this->projectCategory->slug, 'name' => $this->projectCategory->name],
        ];
    }
}

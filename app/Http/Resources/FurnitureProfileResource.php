<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Furniture */
class FurnitureProfileResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, description: string|null, thumbnail: string|null, projectType: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'projectType' => ['slug' => $this->projectCategory->slug, 'name' => $this->projectCategory->name],
        ];
    }
}

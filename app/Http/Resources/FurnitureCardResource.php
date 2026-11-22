<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Furniture */
class FurnitureCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string|null, brand: array{slug: string, name: string}, furnitureType: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'furnitureType' => ['slug' => $this->furnitureType->slug, 'name' => $this->furnitureType->name],
        ];
    }
}

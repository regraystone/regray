<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Furniture */
class FurnitureProfileResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, description: string|null, thumbnail: string|null, brand: array{slug: string, name: string}, furnitureType: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'furnitureType' => ['slug' => Str::slug($this->furniture_type), 'name' => $this->furniture_type],
        ];
    }
}

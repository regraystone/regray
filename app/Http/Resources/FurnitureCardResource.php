<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Furniture */
class FurnitureCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string|null, brand: array{slug: string, name: string, archived?: true}, furnitureType: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
            // `archived` only for an archived brand's, which only a saved card shows: its page is gone.
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name, ...($this->brand->archived_at !== null ? ['archived' => true] : [])],
            'furnitureType' => ['slug' => $this->furnitureType->slug, 'name' => $this->furnitureType->name],
        ];
    }
}

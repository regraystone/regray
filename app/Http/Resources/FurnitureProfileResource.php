<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use App\Models\FurnitureImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Furniture */
class FurnitureProfileResource extends JsonResource
{
    /**
     * `gallery` is the piece's photos besides its thumbnail, in order, each
     * with the caption its alt text reads (the piece's name when it has none).
     * The page shows the thumbnail first, and each photo once.
     *
     * @return array{slug: string, name: string, description: string|null, thumbnail: string|null, brand: array{slug: string, name: string}, furnitureType: array{slug: string, name: string}, gallery: list<array{src: string, name: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'furnitureType' => ['slug' => $this->furnitureType->slug, 'name' => $this->furnitureType->name],
            'gallery' => array_values($this->images->map(fn (FurnitureImage $image): array => [
                'src' => ShowcaseCardResource::resolveThumbnailUrl($image->path),
                'name' => $image->alt_text ?? $this->name,
            ])->all()),
        ];
    }
}

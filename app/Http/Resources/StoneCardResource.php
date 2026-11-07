<?php

namespace App\Http\Resources;

use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class StoneCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string, brand: array{slug: string, name: string}, type: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'type' => ['slug' => $this->type->slug, 'name' => $this->type->name],
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class StoneCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string, recommended: bool, category: array{slug: string, name: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'recommended' => $this->recommended,
            'category' => ['slug' => $this->category->slug, 'name' => $this->category->name],
        ];
    }
}

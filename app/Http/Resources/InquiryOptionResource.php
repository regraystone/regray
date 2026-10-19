<?php

namespace App\Http\Resources;

use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One option of the Application step. The step is named Application, but the
 * options are the project categories, so `id` is the category slug.
 *
 * @mixin ProjectCategory
 */
class InquiryOptionResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, image: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'image' => ProjectCardResource::resolveThumbnailUrl($this->image_path),
        ];
    }
}

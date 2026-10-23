<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class InquiryServiceResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, features: list<string>, image: string, standalone: bool, group: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'features' => array_values(
                $this->features
                    ->sortBy('sort_order')
                    ->map(fn (ServiceFeature $feature): string => $feature->body)
                    ->all(),
            ),
            'image' => ProjectCardResource::resolveThumbnailUrl($this->image_path),
            'standalone' => $this->is_standalone,
            'group' => $this->selection_group,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Furniture;
use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One card in a brand's collection: a stone or a furniture piece, with the kind
 * that says which link and save list it uses.
 *
 * @property-read Stone|Furniture $resource
 */
class BrandProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource instanceof Stone
            ? ['kind' => 'stone', ...(new StoneCardResource($this->resource))->toArray($request)]
            : ['kind' => 'furniture', ...(new FurnitureCardResource($this->resource))->toArray($request)];
    }
}

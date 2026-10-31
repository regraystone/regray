<?php

namespace App\Http\Resources;

use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class InquiryStoneResource extends JsonResource
{
    /**
     * A stone card plus the facts the Inquire page searches over.
     *
     * @return array{slug: string, name: string, thumbnail: string, bestSeller: bool, category: array{slug: string, name: string}, description: string, specifications: array{stoneType: string, origin: string, size: string, thicknessMm: int}}
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new StoneCardResource($this->resource))->toArray($request),
            'description' => $this->category->description,
            'specifications' => [
                'stoneType' => $this->category->name,
                'origin' => $this->category->origin,
                'size' => $this->size,
                'thicknessMm' => $this->thickness_mm,
            ],
        ];
    }
}

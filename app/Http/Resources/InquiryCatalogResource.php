<?php

namespace App\Http\Resources;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the Inquire page offers, as the page's props. Shared by the page
 * that starts an inquiry and the one that edits it, so both offer the same
 * catalogue.
 *
 * @property array{services: Collection<int, Service>, furnitureTypes: Collection<int, FurnitureType>, stones: Collection<int, Stone>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>} $resource
 */
class InquiryCatalogResource extends JsonResource
{
    /**
     * @return array{services: array<int, mixed>, furnitureTypes: array<int, mixed>, stones: array<int, mixed>, edgeProfiles: array<int, mixed>, finishes: array<int, mixed>, locations: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'services' => InquiryServiceResource::collection($this->resource['services'])->resolve($request),
            'furnitureTypes' => $this->resource['furnitureTypes']
                ->map(fn (FurnitureType $type): array => ['id' => $type->slug, 'name' => $type->name])
                ->values()
                ->all(),
            'stones' => InquiryStoneResource::collection($this->resource['stones'])->resolve($request),
            'edgeProfiles' => InquiryFinishingResource::collection($this->resource['edgeProfiles'])->resolve($request),
            'finishes' => InquiryFinishingResource::collection($this->resource['finishes'])->resolve($request),
            'locations' => $this->resource['locations'],
        ];
    }
}

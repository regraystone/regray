<?php

namespace App\Http\Resources;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
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
 * @property array{services: Collection<int, Service>, stones: Collection<int, Stone>, applications: Collection<int, ProjectCategory>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>} $resource
 */
class InquiryCatalogResource extends JsonResource
{
    /**
     * @return array{services: array<int, mixed>, stones: array<int, mixed>, applications: array<int, mixed>, edgeProfiles: array<int, mixed>, finishes: array<int, mixed>, locations: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'services' => InquiryServiceResource::collection($this->resource['services'])->resolve($request),
            'stones' => InquiryStoneResource::collection($this->resource['stones'])->resolve($request),
            'applications' => InquiryOptionResource::collection($this->resource['applications'])->resolve($request),
            'edgeProfiles' => InquiryFinishingResource::collection($this->resource['edgeProfiles'])->resolve($request),
            'finishes' => InquiryFinishingResource::collection($this->resource['finishes'])->resolve($request),
            'locations' => $this->resource['locations'],
        ];
    }
}

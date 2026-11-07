<?php

namespace App\Http\Resources;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the Inquire page offers but its stones, which come a page at a time
 * (ListInquiryStonesAction), as the page's props. Shared by the page
 * that starts an inquiry and the one that edits it, so both offer the same
 * catalogue.
 *
 * @property array{serviceTypes: Collection<int, ServiceType>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>} $resource
 */
class InquiryCatalogResource extends JsonResource
{
    /**
     * @return array{serviceTypes: array<int, mixed>, edgeProfiles: array<int, mixed>, finishes: array<int, mixed>, locations: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'serviceTypes' => InquiryServiceTypeResource::collection($this->resource['serviceTypes'])->resolve($request),
            'edgeProfiles' => InquiryFinishingResource::collection($this->resource['edgeProfiles'])->resolve($request),
            'finishes' => InquiryFinishingResource::collection($this->resource['finishes'])->resolve($request),
            'locations' => $this->resource['locations'],
        ];
    }
}

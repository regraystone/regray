<?php

namespace App\Http\Resources;

use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's first dropdown, with the services its second
 * one then offers.
 *
 * @mixin ServiceType
 */
class InquiryServiceTypeResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, services: array<int, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'services' => InquiryServiceResource::collection($this->services)->resolve($request),
        ];
    }
}

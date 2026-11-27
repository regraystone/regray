<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's first dropdown, with the services its second
 * one then offers. Its applications (Flooring…) are not among them: each is
 * listed under the services that ask one, as the third dropdown.
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
        $applications = $this->services->where('is_application', true)->values();
        $services = $this->services->where('is_application', false)->values()
            ->each(fn (Service $service) => $service->setRelation('applications', $service->has_applications ? $applications : new Collection));

        return [
            'id' => $this->slug,
            'name' => $this->name,
            'services' => InquiryServiceResource::collection($services)->resolve($request),
        ];
    }
}

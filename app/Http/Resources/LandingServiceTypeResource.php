<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A group of services as the landing page lists them ("Natural Stone &
 * Applications", "Custom Stone Furniture"), with the services the Inquire page
 * offers under it, so the landing never lists one a client cannot ask for.
 *
 * @mixin ServiceType
 */
class LandingServiceTypeResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, services: array<int, array{slug: string, name: string, description: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'services' => $this->services
                ->map(fn (Service $service): array => [
                    'slug' => $service->slug,
                    'name' => $service->name,
                    'description' => $service->description,
                ])
                ->values()
                ->all(),
        ];
    }
}

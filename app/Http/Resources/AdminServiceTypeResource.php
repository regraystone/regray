<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A service type on the admin's Services page, a heading over its services,
 * and a row of the Service types drawer.
 *
 * @mixin ServiceType
 */
class AdminServiceTypeResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, name: string, isActive: bool, sortOrder: int, services: array<int, array<string, mixed>>, actions: list<string>}
     */
    public function toArray(Request $request): array
    {
        $gate = Gate::forUser($request->user());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'services' => AdminServiceResource::collection($this->services)->resolve($request),
            'actions' => array_values(array_filter([
                $gate->allows(Permission::ServicesUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::ServicesArchive->value) ? 'archive' : null,
            ])),
        ];
    }
}

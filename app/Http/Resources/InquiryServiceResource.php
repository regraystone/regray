<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's second dropdown, with what is shown under it.
 *
 * @mixin Service
 */
class InquiryServiceResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, description: string, features: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'features' => $this->features,
        ];
    }
}

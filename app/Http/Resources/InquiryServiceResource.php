<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's list of services.
 *
 * @mixin Service
 */
class InquiryServiceResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, needsFurniture: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'needsFurniture' => $this->needs_furniture,
        ];
    }
}

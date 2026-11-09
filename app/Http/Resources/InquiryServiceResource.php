<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\StoneType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's second dropdown, with what is shown under it,
 * and the slugs of the stone types it is made from: a stone fits the service
 * when its primary type is one of them.
 *
 * @mixin Service
 */
class InquiryServiceResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, description: string, features: list<string>, stoneTypes: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'features' => $this->features,
            'stoneTypes' => array_values($this->stoneTypes->map(fn (StoneType $type): string => $type->slug)->all()),
        ];
    }
}

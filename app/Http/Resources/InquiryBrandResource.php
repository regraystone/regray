<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand as the Inquire page offers it: a card on the Brand question and an
 * entry of the stones' Brand filter, by its slug, with its logo.
 *
 * @mixin Brand
 */
class InquiryBrandResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, logo: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'logo' => ProjectCardResource::resolveThumbnailUrl($this->logo_path),
        ];
    }
}

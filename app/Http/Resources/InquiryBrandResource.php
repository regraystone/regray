<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand as the Inquire page offers it: a card on the Brand question and an
 * entry of the stones' Brand filter, by its slug, with its logo. `available`
 * is whether it has an active stone or piece of furniture
 * (BrandRepositoryInterface::activeForInquiry()); the Brand question shows
 * one without as Unavailable, and it cannot be picked.
 *
 * @mixin Brand
 */
class InquiryBrandResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, logo: string, available: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'logo' => ShowcaseCardResource::resolveThumbnailUrl($this->logo_path),
            'available' => (bool) $this->getAttribute('has_products'),
        ];
    }
}

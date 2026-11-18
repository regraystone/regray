<?php

namespace App\Http\Resources;

use App\Data\ClientInformationValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The two "Client Information" values, without ids or a user id: a page is only
 * ever sent its own reader's values. Used for both the saved row on the Profile
 * page and a read-only inquiry snapshot.
 *
 * @mixin ClientInformationValues
 */
class ClientInformationResource extends JsonResource
{
    /** @return array{deliveryMethod: string, location: string|null} */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}

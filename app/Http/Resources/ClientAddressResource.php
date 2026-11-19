<?php

namespace App\Http\Resources;

use App\Data\PhAddress;
use App\Models\ClientAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One address of the client's own address book, for the Addresses page and
 * the Inquire page's address choice.
 *
 * @mixin ClientAddress
 *
 * @phpstan-import-type PhAddressPayload from PhAddress
 */
class ClientAddressResource extends JsonResource
{
    /**
     * @return array{id: int, label: string, isDefault: bool, address: PhAddressPayload}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'isDefault' => $this->is_default,
            'address' => $this->address()->toPayload(),
        ];
    }
}

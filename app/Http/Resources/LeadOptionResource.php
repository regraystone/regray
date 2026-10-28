<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead staff may open an inquiry from, as the Inquire page's lead step lists
 * it. Expects the client loaded.
 *
 * @mixin Lead
 */
class LeadOptionResource extends JsonResource
{
    /** @return array{reference: string, status: string, clientId: int, client: string} */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'clientId' => $this->client_id,
            'client' => $this->client->name,
        ];
    }
}

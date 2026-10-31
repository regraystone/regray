<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead staff may open an inquiry from, as the Inquire page's lead step lists
 * it, with the tally of its inquiries' decisions. Expects the client and the
 * inquiries' decisions loaded.
 *
 * @mixin Lead
 */
class LeadOptionResource extends JsonResource
{
    /** @return array{reference: string, tally: array{won: int, lost: int, idle: int}, clientId: int, client: string} */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'tally' => $this->tally(),
            'clientId' => $this->client_id,
            'client' => $this->client->name,
        ];
    }
}

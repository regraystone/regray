<?php

namespace App\Http\Resources\Message;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somebody a group can be started with: a name, and for staff in a picker
 * (selected with User::scopeWithActiveInquiryCount()) how many open
 * inquiries they are assigned to, which is informational only. A client
 * found by a search carries the `label` it is shown by
 * (User::searchLabel()): their name, or the email the search matched.
 *
 * @mixin User
 */
class PersonResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, label?: string, activeInquiryCount?: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->whenHas('label'),
            'activeInquiryCount' => $this->whenHas('active_inquiry_count', fn ($count): int => (int) $count),
        ];
    }
}

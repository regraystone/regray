<?php

namespace App\Http\Resources;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line in the bell. Where it leads is not sent: it is worked out when the
 * line is opened (NotificationService::open()), against what the reader may
 * open by then.
 *
 * @mixin UserNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'body' => $this->body,
            'isRead' => $this->read_at !== null,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}

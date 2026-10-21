<?php

namespace App\Http\Resources\Message;

use App\Enums\BookedByType;
use App\Models\Message;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visit as a card in a thread. The status is the one the card announces.
 *
 * Nothing about the client is sent — no contact details, no other visits — and
 * the staff member's name only when staff made the booking. Whether the viewer
 * may see the visit at all is decided by MessageResource before this is built.
 *
 * @mixin Message
 */
class ScheduleCardResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, arrivalAt: string, announcedStatus: string, bookedByType: string, bookedByName: string|null, inquiryReference: string|null, cancelReason: string|null, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var Schedule $schedule */
        $schedule = $this->schedule;
        $isStaffBooking = $schedule->booked_by_type === BookedByType::Staff;

        return [
            'id' => $schedule->id,
            'title' => $schedule->title,
            'arrivalAt' => $schedule->arrival_at->toIso8601String(),
            'announcedStatus' => (string) $this->announced_status,
            'bookedByType' => $schedule->booked_by_type->value,
            'bookedByName' => $isStaffBooking ? $schedule->bookedBy?->name : null,
            'inquiryReference' => $schedule->inquiry?->reference,
            'cancelReason' => $this->announced_status === 'cancelled' ? $schedule->cancel_reason : null,
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}

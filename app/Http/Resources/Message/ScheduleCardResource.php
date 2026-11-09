<?php

namespace App\Http\Resources\Message;

use App\Enums\BookedByType;
use App\Enums\ScheduleCardStatus;
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
     * The arrival and the reason are the card's own, as the change it announces
     * left them, so an older card keeps its time after the appointment moves. A
     * card made before snapshots existed falls back to the appointment's.
     *
     * @return array{id: int, title: string, arrivalAt: string, announcedStatus: string, bookedByType: string, bookedByName: string|null, inquiryReference: string|null, cancelReason: string|null, changeReason: string|null, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var Schedule $schedule */
        $schedule = $this->schedule;
        $isStaffBooking = $schedule->booked_by_type === BookedByType::Staff;
        $announced = (string) $this->announced_status;
        $reason = $this->announced_reason;

        return [
            'id' => $schedule->id,
            'title' => $schedule->title,
            'arrivalAt' => ($this->announced_arrival_at ?? $schedule->arrival_at)->toIso8601String(),
            'announcedStatus' => $announced,
            'bookedByType' => $schedule->booked_by_type->value,
            'bookedByName' => $isStaffBooking ? $schedule->bookedBy?->name : null,
            'inquiryReference' => $schedule->inquiry?->reference,
            'cancelReason' => $announced === ScheduleCardStatus::Cancelled->value ? ($reason ?? $schedule->cancel_reason) : null,
            'changeReason' => $announced === ScheduleCardStatus::Rescheduled->value ? $reason : null,
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}

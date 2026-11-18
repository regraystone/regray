<?php

namespace App\Enums;

/**
 * What a change to an appointment was (ScheduleChanged), for the card it posts
 * in the client's Regray thread and the notifications it sends.
 *
 * Assigning and declining are steps between marketing and sales: the client
 * still reads the appointment as pending, so they post no card.
 */
enum ScheduleChangeKind: string
{
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Assigned = 'assigned';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Served = 'served';
    case Expired = 'expired';

    /** The card it posts in the client's thread, or null for a step the client does not see. */
    public function card(): ?ScheduleCardStatus
    {
        return match ($this) {
            self::Rescheduled => ScheduleCardStatus::Rescheduled,
            self::Cancelled => ScheduleCardStatus::Cancelled,
            self::Confirmed => ScheduleCardStatus::Confirmed,
            self::Served => ScheduleCardStatus::Served,
            self::Expired => ScheduleCardStatus::Expired,
            self::Assigned, self::Declined => null,
        };
    }
}

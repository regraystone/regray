<?php

namespace App\Enums;

/**
 * What a schedule card in the client's Regray thread announces.
 *
 * The appointment's own statuses, plus `rescheduled`, which is a change rather
 * than a status: an updated appointment is pending or confirmed again, and its
 * card says it moved. Mirrors `CardScheduleStatus` in lib/thread-cards.ts and
 * the schedule-card values in chk_messages_type.
 */
enum ScheduleCardStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Served = 'served';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * The card a status of its own announces, as the client reads it:
     * awaiting sales is pending (ScheduleStatus::forClient()).
     */
    public static function forStatus(ScheduleStatus $status): self
    {
        return self::from($status->forClient()->value);
    }
}

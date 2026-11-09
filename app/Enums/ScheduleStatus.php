<?php

namespace App\Enums;

/**
 * Where an appointment has got to.
 *
 * A client's booking starts pending until marketing or sales approve it; a
 * staff booking and an inquiry's visit start confirmed. A confirmed appointment
 * becomes served when staff mark it as attended. One still pending when its
 * time passes becomes expired (`schedules:expire`). Cancelled always carries
 * when and why.
 */
enum ScheduleStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Served = 'served';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Whether it is still to happen: it can be changed, approved or cancelled. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}

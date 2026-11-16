<?php

namespace App\Enums;

use App\Models\User;

/**
 * Where an appointment has got to.
 *
 * An appointment booked outside an inquiry is confirmed by sales: a client's
 * booking starts pending until marketing assigns it to a sales member, and
 * one marketing books starts assigned. Assigned, it is awaiting sales until
 * that sales member confirms it (confirmed) or declines it (pending again, for
 * marketing to assign it again). Moving an assigned appointment sends it back
 * to awaiting sales. An inquiry's visit starts confirmed and follows its
 * inquiry. A confirmed appointment becomes served when staff mark it as
 * attended. One still pending or awaiting sales when its time passes becomes
 * expired (`schedules:expire`). Cancelled always carries when and why.
 *
 * A client never reads awaiting sales: to them it is pending (forClient()), as
 * an inquiry's role statuses are read as one display status.
 */
enum ScheduleStatus: string
{
    case Pending = 'pending';
    case AwaitingSales = 'awaiting_sales';
    case Confirmed = 'confirmed';
    case Served = 'served';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Whether it is still to happen: it can be changed, assigned, confirmed or cancelled. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::AwaitingSales || $this === self::Confirmed;
    }

    /** Whether it still waits on somebody to agree to it: expired once its time passes. */
    public function isUnconfirmed(): bool
    {
        return $this === self::Pending || $this === self::AwaitingSales;
    }

    /** The status a client reads: awaiting sales is still pending to them. */
    public function forClient(): self
    {
        return $this === self::AwaitingSales ? self::Pending : $this;
    }

    /** The status `$viewer` reads: forClient() for a client, the status itself for staff. */
    public function for(?User $viewer): self
    {
        return $viewer !== null && $viewer->isClient() ? $this->forClient() : $this;
    }

    /** @return list<self> */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }

    /** @return list<self> */
    public static function unconfirmed(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isUnconfirmed()));
    }
}

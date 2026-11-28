<?php

namespace App\Enums;

/**
 * What a booking is for (`schedules.kind`): a visit to the showroom, or the
 * installation or the delivery of one of the client's accepted orders.
 *
 * An inquiry's own target date has no kind: it is not a booking.
 */
enum ScheduleKind: string
{
    case ShowroomVisit = 'showroom_visit';
    case Installation = 'installation';
    case Delivery = 'delivery';

    /** "Installation", as the Schedule page and the notifications name it. */
    public function label(): string
    {
        return match ($this) {
            self::ShowroomVisit => 'Showroom visit',
            self::Installation => 'Installation',
            self::Delivery => 'Delivery',
        };
    }

    /** Whether it is booked for one of the client's orders. */
    public function isForOrder(): bool
    {
        return $this !== self::ShowroomVisit;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $kind): string => $kind->value, self::cases());
    }
}

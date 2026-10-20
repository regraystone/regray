<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Quoted = 'quoted';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The statuses a list can be filtered by. `draft` is history only: no
     * inquiry sits at it, so offering it would filter to nothing.
     *
     * @return list<string>
     */
    public static function listable(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status !== self::Draft),
        ));
    }
}

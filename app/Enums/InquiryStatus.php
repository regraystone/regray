<?php

namespace App\Enums;

/**
 * An inquiry's status, read from its projects': lost when every project is
 * cancelled, closed when none is still open and one is closed, ongoing once
 * work has started on any, pending while every project is.
 */
enum InquiryStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Ongoing = 'ongoing';
    case Closed = 'closed';
    case Lost = 'lost';

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

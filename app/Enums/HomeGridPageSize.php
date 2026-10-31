<?php

namespace App\Enums;

/**
 * How many cards the home collection loads per page: five rows of the grid at
 * each screen size (2 columns on a phone, 4 on a tablet or small laptop, 5
 * from 1280px), so every page ends on a full row. The page sends the size for
 * its screen as `per_page`; without one, `Tablet` is used, as 20 fills every
 * grid's rows.
 */
enum HomeGridPageSize: int
{
    case Phone = 10;
    case Tablet = 20;
    case Desktop = 25;

    public const DEFAULT = self::Tablet;
}

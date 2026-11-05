<?php

namespace App\Enums;

/**
 * How many products a brand's collection loads at a time: whole rows of its
 * two-column phone grid and its three-column grid from tablet up.
 */
enum HomeGridPageSize: int
{
    case Phone = 16;
    case Tablet = 24;

    public const DEFAULT = self::Tablet;

    /**
     * The browser sets this from the screen width, because the server cannot
     * see the screen and the size stays out of the URL. Read as plain text, so
     * it is listed with the unencrypted cookies in bootstrap/app.php.
     */
    public const COOKIE = 'grid_page_size';
}

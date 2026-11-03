<?php

namespace App\Enums;

enum HomeGridPageSize: int
{
    case Phone = 10;
    case Tablet = 15;

    public const DEFAULT = self::Tablet;
}

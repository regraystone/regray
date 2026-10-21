<?php

namespace App\Enums;

/** What a `system` message records on a Regray Team thread. */
enum SystemEvent: string
{
    case Assigned = 'assigned';
    case Released = 'released';
    case Reassigned = 'reassigned';
}

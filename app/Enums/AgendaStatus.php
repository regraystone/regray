<?php

namespace App\Enums;

enum AgendaStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

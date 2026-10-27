<?php

namespace App\Enums;

/** Marketing's status: pending until the client is engaged (won) or the inquiry is dropped (lost). */
enum MarketingStatus: string
{
    case Pending = 'pending';
    case Won = 'won';
    case Lost = 'lost';
}

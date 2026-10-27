<?php

namespace App\Enums;

/**
 * A payment's status, which the amounts have to agree with: awaiting has
 * nothing paid, partial has some, fully_paid has all of it. A partial or
 * fully paid payment can be refunded.
 */
enum PaymentStatus: string
{
    case Awaiting = 'awaiting';
    case Partial = 'partial';
    case FullyPaid = 'fully_paid';
    case Refunded = 'refunded';
}

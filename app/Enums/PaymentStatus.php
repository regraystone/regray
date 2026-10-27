<?php

namespace App\Enums;

/**
 * Accounting's status, set by hand as payments come in: awaiting, then
 * partial, then fully paid. Not derived from the payment transactions.
 */
enum PaymentStatus: string
{
    case Awaiting = 'awaiting';
    case Partial = 'partial';
    case FullyPaid = 'fully_paid';
}

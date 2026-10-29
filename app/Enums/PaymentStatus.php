<?php

namespace App\Enums;

/**
 * Accounting's status, set by hand as payments come in: awaiting, then
 * partial, then fully paid. Not derived from the payment transactions.
 *
 * `refunded` is reserved for a refund flow that is still to be designed (who
 * starts a refund, from which statuses, and with which documents). Until that
 * flow exists nothing sets it: it is in no track's targets, and it is final.
 */
enum PaymentStatus: string
{
    case Awaiting = 'awaiting';
    case Partial = 'partial';
    case FullyPaid = 'fully_paid';
    case Refunded = 'refunded';
}

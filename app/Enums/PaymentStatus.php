<?php

namespace App\Enums;

/**
 * Accounting's status, set by hand as payments come in: awaiting, then
 * partial, then fully paid. Not derived from the payment transactions.
 *
 * Once sales has moved the inquiry to for refund, accounting refunds it:
 * partially refunded while part of what was paid is still held, then refunded
 * once all of it has gone back, each with the refund settled file and the
 * amount. Refunded is final.
 */
enum PaymentStatus: string
{
    case Awaiting = 'awaiting';
    case Partial = 'partial';
    case FullyPaid = 'fully_paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}

<?php

namespace App\Enums;

/**
 * A project's status. Pending until work starts, then ongoing. Cancelled when
 * its quotation is rejected, its payment refunded or a job order cancelled;
 * closed when the quotation is closed, the payment fully paid and every job
 * order completed.
 */
enum InquiryProjectStatus: string
{
    case Pending = 'pending';
    case Ongoing = 'ongoing';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}

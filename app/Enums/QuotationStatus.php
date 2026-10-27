<?php

namespace App\Enums;

/**
 * A quotation revision's status: pending (with the client), closeable
 * (accepted), closed. A pending revision can instead become revision (replaced
 * by a newer one) or rejected.
 */
enum QuotationStatus: string
{
    case Pending = 'pending';
    case Closed = 'closed';
    case Revision = 'revision';
    case Closeable = 'closeable';
    case Rejected = 'rejected';
}

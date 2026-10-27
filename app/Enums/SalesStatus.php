<?php

namespace App\Enums;

/**
 * Sales' status. Negotiation, site visits and holds while sales talks with the
 * client, and through every quotation issued. Quoted only once the client has
 * approved the quotation. Rejected when the client turns it down or cancels.
 */
enum SalesStatus: string
{
    case Negotiation = 'negotiation';
    case SiteVisited = 'site_visited';
    case OnHold = 'on_hold';
    case Quoted = 'quoted';
    case Rejected = 'rejected';
}

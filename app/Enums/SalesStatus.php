<?php

namespace App\Enums;

/**
 * Sales' status. Negotiation, site visits and holds while sales talks with the
 * client. Quoted once the first quotation has been sent, revision for each
 * revised one after it (each a new quotation revision), and won once the
 * client has approved it: won is final, and starts accounting's payment and,
 * with the job order, logistics. Revision and won need a quotation sent first.
 * Rejected when the client turns it down or cancels.
 */
enum SalesStatus: string
{
    case Negotiation = 'negotiation';
    case SiteVisited = 'site_visited';
    case OnHold = 'on_hold';
    case Quoted = 'quoted';
    case Revision = 'revision';
    case Won = 'won';
    case Rejected = 'rejected';
}

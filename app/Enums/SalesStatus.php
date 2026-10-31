<?php

namespace App\Enums;

/**
 * Sales' status. Negotiation, site visits and holds while sales talks with the
 * client. Quoted once the first quotation has been sent, revision for each
 * revised one after it (each a new quotation revision), and won once the
 * client has approved it, with the job order: won starts accounting's payment,
 * and moves only to for refund. Revision and won need a quotation sent first.
 * Rejected when the client turns it down or cancels before approving. For
 * refund is the client asking for their money back after approving, with the
 * refund request form; accounting then refunds it, and the full refund moves
 * it to rejected on its own. Rejected is final, and for refund is by hand.
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
    case ForRefund = 'for_refund';
}

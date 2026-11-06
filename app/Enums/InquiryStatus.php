<?php

namespace App\Enums;

/**
 * The status shown for an inquiry everywhere, to the client and to staff: the
 * stage it has reached. Calculated from marketing's decision and the three
 * role statuses by App\Services\InquiryStatusCalculator, never stored.
 *
 * The stage values are the role values they come from: a sales status while
 * sales has it, a logistics status once logistics has started. `pending` is
 * before sales has started, `closed` is logistics completed, `cancelled` is
 * marketing lost, sales rejected or accounting refunded all of it, and
 * `for_refund` is sales asking for a refund until accounting has refunded it
 * all.
 *
 * Sales won shows as `quoted`, so `won` is no longer calculated: it is only on
 * inquiry cards posted before that, which keep what they announced.
 */
enum InquiryStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
    case Negotiation = 'negotiation';
    case SiteVisited = 'site_visited';
    case OnHold = 'on_hold';
    case Quoted = 'quoted';
    case Revision = 'revision';
    case Won = 'won';
    case OrderReceived = 'order_received';
    case Measure = 'measure';
    case Sourcing = 'sourcing';
    case Production = 'production';
    case QualityCheck = 'quality_check';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Installation = 'installation';
    case Delayed = 'delayed';
    case ForRefund = 'for_refund';

    /** The tab on the Quote list this status is filtered under. */
    public function group(): InquiryStatusGroup
    {
        return match ($this) {
            self::Pending => InquiryStatusGroup::Pending,
            self::Closed => InquiryStatusGroup::Closed,
            self::Cancelled => InquiryStatusGroup::Cancelled,
            default => InquiryStatusGroup::InProgress,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}

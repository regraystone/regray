<?php

namespace App\Enums;

/**
 * The status shown for an inquiry everywhere, to the client and to staff: the
 * stage it has reached. Calculated from the inquiry's lead and its three role
 * statuses by App\Services\InquiryStatusCalculator, never stored.
 *
 * The stage values are the role values they come from: a sales status while
 * sales has it, a logistics status once the job order is in. `pending` is
 * before sales has started, `closed` is logistics completed, and `cancelled`
 * is the lead lost or sales rejected.
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
    case OrderReceived = 'order_received';
    case Measure = 'measure';
    case Sourcing = 'sourcing';
    case Production = 'production';
    case QualityCheck = 'quality_check';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Installation = 'installation';
    case Delayed = 'delayed';

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

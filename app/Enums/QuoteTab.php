<?php

namespace App\Enums;

/**
 * The tabs of the client's Quotations page, past All: where an inquiry
 * stands from the client's side. Pending is not quoted yet, Quoted has a
 * quotation waiting on them, Accepted is an order under way or done. A
 * cancelled inquiry, or one being refunded, is under All only.
 */
enum QuoteTab: string
{
    case Pending = 'pending';
    case Quoted = 'quoted';
    case Accepted = 'accepted';

    /**
     * The display statuses (InquiryStatus) the tab lists. Won is only on
     * older inquiries, from before sales won showed as quoted.
     *
     * @return list<InquiryStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Pending => [InquiryStatus::Pending, InquiryStatus::Negotiation, InquiryStatus::SiteVisited, InquiryStatus::OnHold],
            self::Quoted => [InquiryStatus::Quoted, InquiryStatus::Revision, InquiryStatus::Won],
            self::Accepted => [
                InquiryStatus::OrderReceived, InquiryStatus::Measure, InquiryStatus::Sourcing, InquiryStatus::Production,
                InquiryStatus::QualityCheck, InquiryStatus::Ready, InquiryStatus::Dispatched, InquiryStatus::Installation,
                InquiryStatus::Delayed, InquiryStatus::Closed,
            ],
        };
    }

    /** The tab a status is listed under, or null for one under All only. */
    public static function forStatus(InquiryStatus $status): ?self
    {
        foreach (self::cases() as $tab) {
            if (in_array($status, $tab->statuses(), true)) {
                return $tab;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $tab): string => $tab->value, self::cases());
    }
}

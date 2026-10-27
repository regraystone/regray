<?php

namespace App\Enums;

use BackedEnum;

/**
 * The four role statuses of an inquiry, each its own track in the status
 * history: which column it is stored in, and which enum its values are.
 */
enum InquiryStatusTrack: string
{
    case Marketing = 'marketing';
    case Sales = 'sales';
    case Payment = 'payment';
    case Process = 'process';

    /** The `inquiries` column this track's current value is stored in. */
    public function column(): string
    {
        return match ($this) {
            self::Marketing => 'marketing_status',
            self::Sales => 'sales_status',
            self::Payment => 'payment_status',
            self::Process => 'process_status',
        };
    }

    /** @return class-string<BackedEnum> */
    public function enum(): string
    {
        return match ($this) {
            self::Marketing => MarketingStatus::class,
            self::Sales => SalesStatus::class,
            self::Payment => PaymentStatus::class,
            self::Process => ProcessStatus::class,
        };
    }

    /** A stored value of this track, as its enum. */
    public function status(string $value): MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus
    {
        return ($this->enum())::from($value);
    }
}

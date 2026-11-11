<?php

namespace App\Enums;

use BackedEnum;

/**
 * The three role statuses of an inquiry, each its own track in the status
 * history: which column it is stored in, and which enum its values are.
 * Marketing's status is not one of them: it is the inquiry's lead's
 * (LeadStatus).
 */
enum InquiryStatusTrack: string
{
    case Sales = 'sales';
    case Payment = 'payment';
    case Process = 'process';

    /** The `inquiries` column this track's current value is stored in. */
    public function column(): string
    {
        return match ($this) {
            self::Sales => 'sales_status',
            self::Payment => 'payment_status',
            self::Process => 'process_status',
        };
    }

    /** @return class-string<BackedEnum> */
    public function enum(): string
    {
        return match ($this) {
            self::Sales => SalesStatus::class,
            self::Payment => PaymentStatus::class,
            self::Process => ProcessStatus::class,
        };
    }

    /** A stored value of this track, as its enum. */
    public function status(string $value): SalesStatus|PaymentStatus|ProcessStatus
    {
        return ($this->enum())::from($value);
    }

    /** The team that moves this track, as staff notifications name it. */
    public function team(): string
    {
        return match ($this) {
            self::Sales => 'sales',
            self::Payment => 'accounting',
            self::Process => 'logistics',
        };
    }
}

<?php

namespace App\Events;

use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One of an inquiry's role statuses moved, on `$track`. Dispatched by every
 * transition through InquiryStatusService::transition(), and for a new inquiry
 * (marketing, from nothing to pending), only once the transaction that made
 * the change has committed.
 */
class InquiryStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public InquiryStatusTrack $track,
        public MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus|null $from,
        public MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $to,
        public ?int $changedBy,
    ) {}
}

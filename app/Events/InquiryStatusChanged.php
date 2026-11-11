<?php

namespace App\Events;

use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * What an inquiry shows changed, only once the transaction that made the change
 * has committed. It posts the inquiry's card (PostInquiryStatusCard).
 *
 * One of its role statuses moved on `$track`, from `$from` to `$to`, in every
 * transition through InquiryStatusService::transition(). `$track`, `$from` and
 * `$to` are all null when no role status moved: the inquiry was just sent, or
 * its lead was lost.
 *
 * `$decision` is marketing's, when the change is one (won, which starts
 * sales, or lost). `$opening` marks the moves made while the inquiry is being
 * opened, which SendInquiryNotifications leaves to InquirySubmitted.
 */
class InquiryStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public ?InquiryStatusTrack $track,
        public SalesStatus|PaymentStatus|ProcessStatus|null $from,
        public SalesStatus|PaymentStatus|ProcessStatus|null $to,
        public ?int $changedBy,
        public ?LeadStatus $decision = null,
        public bool $opening = false,
    ) {}
}

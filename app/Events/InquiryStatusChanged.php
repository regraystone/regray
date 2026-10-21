<?php

namespace App\Events;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An inquiry moved from one status to another. Dispatched by every transition
 * through InquiryStatusService::transition(), and only once the transaction
 * that made the change has committed.
 */
class InquiryStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public ?InquiryStatus $from,
        public InquiryStatus $to,
        public ?int $changedBy,
    ) {}
}

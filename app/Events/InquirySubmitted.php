<?php

namespace App\Events;

use App\Models\Inquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new inquiry, once committed: sent by its client (`$createdBy` null) or
 * opened by staff (`$createdBy` is the staff member). SendInquiryNotifications
 * tells the client and the staff concerned.
 */
class InquirySubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public ?int $createdBy,
    ) {}
}

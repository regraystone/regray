<?php

namespace App\Events;

use App\Models\Inquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The client changed their own inquiry (`updated`) or cancelled it
 * (`cancelled`), once committed. SendInquiryNotifications confirms it to them
 * and tells the staff concerned. A cancel's status moves are announced by this
 * event alone.
 */
class InquiryChangedByClient implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public const UPDATED = 'updated';

    public const CANCELLED = 'cancelled';

    /**
     * @param  self::UPDATED|self::CANCELLED  $change
     */
    public function __construct(
        public Inquiry $inquiry,
        public string $change,
    ) {}
}

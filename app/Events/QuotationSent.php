<?php

namespace App\Events;

use App\Models\InquiryFile;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sales sent a quotation, the first or a revision: `$file` is the document,
 * stored with the move to quoted or revision. Dispatched only once that move
 * has committed, and after the move's own InquiryStatusChanged, so the
 * quotation card (PostQuotationCard) is queued after the inquiry card.
 */
class QuotationSent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public InquiryFile $file,
    ) {}
}

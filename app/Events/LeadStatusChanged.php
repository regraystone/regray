<?php

namespace App\Events;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead was won or lost. Dispatched by LeadService::transition() inside its
 * transaction, so ApplyLeadStatusCascade moves the lead's inquiries in the same
 * transaction: the lead and its inquiries never disagree.
 */
class LeadStatusChanged
{
    use Dispatchable;

    public function __construct(
        public Lead $lead,
        public LeadStatus $from,
        public LeadStatus $to,
        public ?int $changedBy,
    ) {}
}

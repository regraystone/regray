<?php

namespace App\Listeners;

use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\SalesStatus;
use App\Events\InquiryStatusChanged;
use App\Events\LeadStatusChanged;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\InquiryStatusCalculator;
use App\Services\InquiryStatusService;
use App\Services\ScheduleService;
use Illuminate\Support\Facades\Date;

/**
 * What a lead's status does to its inquiries, in the transaction that changed
 * the lead (so not queued):
 *
 * - **won:** every inquiry whose sales has not started starts sales at
 *   negotiation, through InquiryStatusService::transition(), with its history
 *   row and its card.
 * - **lost:** every inquiry now shows cancelled. Each one that was not
 *   cancelled already has its open visits cancelled and posts a card; no role
 *   status moves, so no history row is written.
 *
 * A card is posted only for an inquiry whose display status changed.
 */
class ApplyLeadStatusCascade
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiries,
        public readonly InquiryStatusService $inquiryStatusService,
        public readonly ScheduleService $scheduleService,
    ) {}

    public function handle(LeadStatusChanged $event): void
    {
        $by = $event->changedBy === null ? null : User::query()->find($event->changedBy);
        $now = Date::now();

        foreach ($this->inquiries->lockForLead($event->lead) as $inquiry) {
            $inquiry->setRelation('lead', $event->lead);

            if ($event->to === LeadStatus::Won && $inquiry->sales_status === null) {
                $this->inquiryStatusService->transition($inquiry, InquiryStatusTrack::Sales, SalesStatus::Negotiation, $by);

                continue;
            }

            if ($event->to !== LeadStatus::Lost) {
                continue;
            }

            $before = InquiryStatusCalculator::calculate($event->from, $inquiry->sales_status, $inquiry->process_status, $inquiry->hasJobOrder());

            if ($before === InquiryStatus::Cancelled) {
                continue;
            }

            $this->scheduleService->cancelForInquiry($inquiry, $now);

            event(new InquiryStatusChanged($inquiry, null, null, null, $event->changedBy));
        }
    }
}

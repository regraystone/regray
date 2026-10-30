<?php

namespace App\Services;

use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Every change to one of an inquiry's role statuses goes through transition(),
 * which writes the status, its history row on that track and the
 * InquiryStatusChanged event together.
 *
 * The event posts an inquiry card (PostInquiryStatusCard): into the inquiry's
 * group chat when it has one, otherwise into the client's Regray Team thread.
 *
 * Marketing's decision on an inquiry goes through decide(): won starts its
 * sales, lost shows it cancelled. Staff move the sales, payment and logistics
 * tracks by hand through InquiryStatusUpdateService; the client's cancel,
 * marketing's decisions and an inquiry opened by sales or for a direct client
 * move them here too.
 */
class InquiryStatusService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly InquiryActions $inquiryActions,
        public readonly ScheduleService $scheduleService,
        public readonly LeadRepositoryInterface $leads,
    ) {}

    /**
     * Cancel the client's own inquiry, before they have approved the quotation.
     * Once sales has started, sales becomes rejected. Before it has, the
     * inquiry is decided lost, by the client: that inquiry alone, so the
     * client's other inquiries on the lead carry on. An inquiry with no lead
     * yet has nobody to decide it, so its sales becomes rejected straight away.
     * After approval the client cannot cancel; only sales can, by rejecting.
     *
     * Has to run inside a transaction: the row is locked until it ends, so two
     * cancels at once cannot both read an open inquiry and both write a history
     * row. The one that waits finds it cancelled and is refused.
     *
     * @throws ModelNotFoundException When the reference is not this client's.
     * @throws ValidationException When the inquiry is no longer open.
     */
    public function cancel(User $client, string $reference): Inquiry
    {
        $inquiry = $this->inquiryRepository->findForClientByReferenceForUpdate($client, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        $cancel = $this->inquiryActions->cancel($inquiry);

        // The button table is the rule: where Cancel is not shown, or is shown
        // and locked, the request is refused too.
        if (! $cancel->visible || ! $cancel->enabled) {
            throw ValidationException::withMessages([
                'status' => $cancel->reason ?? 'Only open inquiries can be cancelled.',
            ]);
        }

        if ($inquiry->sales_status !== null || $inquiry->lead_id === null) {
            $this->transition($inquiry, InquiryStatusTrack::Sales, SalesStatus::Rejected, $client);

            return $inquiry;
        }

        $this->decide($inquiry, LeadStatus::Lost, $client, 'Cancelled by the client.');

        return $inquiry;
    }

    /**
     * Marketing's decision on one of a lead's inquiries, with the note saying
     * why. Won starts its sales at negotiation, with that track's history row
     * and card. Lost shows it cancelled: its open visits are cancelled and its
     * card posted. Each decision is a row of the lead's log naming the
     * inquiry; the lead's own status is the tally of these.
     *
     * Only an inquiry still waiting for marketing may be decided: it is on a
     * lead, undecided, and its sales has not started. Expects the inquiry to be
     * locked already, by whoever decided the change is allowed.
     *
     * @throws ValidationException When the inquiry is not waiting for a decision, or `$to` is idle.
     */
    public function decide(Inquiry $inquiry, LeadStatus $to, ?User $by, ?string $note = null): void
    {
        if ($to === LeadStatus::Idle) {
            throw ValidationException::withMessages(['to' => 'An inquiry can only be marked won or lost.']);
        }

        if ($inquiry->marketing_status !== null) {
            throw ValidationException::withMessages(['to' => "{$inquiry->reference} is already {$inquiry->marketing_status->value}."]);
        }

        if ($inquiry->lead_id === null || $inquiry->sales_status !== null) {
            throw ValidationException::withMessages(['to' => "{$inquiry->reference} is not waiting for marketing."]);
        }

        $now = Date::now();

        $this->inquiryRepository->recordMarketingStatus($inquiry, $to, $now);
        $this->leads->createHistory($this->leads->lockForUpdate($inquiry->lead_id), null, $to, $by?->id, $now, $note, $inquiry->id);

        if ($to === LeadStatus::Won) {
            $this->transition($inquiry, InquiryStatusTrack::Sales, SalesStatus::Negotiation, $by);

            return;
        }

        $this->scheduleService->cancelForInquiry($inquiry, $now);

        event(new InquiryStatusChanged($inquiry, null, null, null, $by?->id));
    }

    /**
     * Move one of an inquiry's role statuses: the status and when it changed, a
     * history row on its track saying who changed it and why (`$note`), and the
     * event that posts the status card once the transaction commits. A track
     * whose stage has not started moves from nothing (NULL). Returns the
     * history row, which the files of the update point to.
     *
     * What may move where, and what a move has to carry, is the caller's
     * question (InquiryStatusUpdateService, InquiryStatusRules).
     *
     * Expects the inquiry to be locked already, by whoever decided the change
     * is allowed. Sales rejected cancels the inquiry's open visits in the same
     * transaction.
     */
    public function transition(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        SalesStatus|PaymentStatus|ProcessStatus $to,
        ?User $by,
        ?string $note = null,
    ): InquiryStatusHistory {
        if (! $to instanceof ($track->enum())) {
            throw new InvalidArgumentException("{$to->value} is not a {$track->value} status.");
        }

        $from = $inquiry->{$track->column()};
        $now = Date::now();

        $this->inquiryRepository->updateStatus($inquiry, $track, $to, $now);
        $history = $this->inquiryRepository->createStatusHistory($inquiry, $track, $from, $to, $by?->id, $now, $note);

        if ($to === SalesStatus::Rejected) {
            $this->scheduleService->cancelForInquiry($inquiry, $now);
        }

        event(new InquiryStatusChanged($inquiry, $track, $from, $to, $by?->id));

        return $history;
    }
}

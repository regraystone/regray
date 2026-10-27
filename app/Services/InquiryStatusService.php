<?php

namespace App\Services;

use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
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
 * The flow between the statuses (who moves which track, and when) is not built
 * yet; see docs/database/regray-erd.md. Only the client's cancel uses this.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then
 * no real inquiry has a group, so every card goes to the Regray Team thread.
 */
class InquiryStatusService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly InquiryActions $inquiryActions,
        public readonly ScheduleService $scheduleService,
    ) {}

    /**
     * Cancel the client's own inquiry, before they have approved the quotation:
     * sales becomes rejected once sales has started, and marketing becomes lost
     * before it has. Either shows the inquiry as cancelled. After approval the
     * client cannot cancel; only sales can, by rejecting. Further conditions
     * are not decided yet beyond the button table.
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

        return $inquiry->sales_status === null
            ? $this->transition($inquiry, InquiryStatusTrack::Marketing, MarketingStatus::Lost, $client)
            : $this->transition($inquiry, InquiryStatusTrack::Sales, SalesStatus::Rejected, $client);
    }

    /**
     * Move one of an inquiry's role statuses: the status and when it changed, a
     * history row on its track saying who changed it, and the event that posts
     * the status card once the transaction commits. A track whose stage has not
     * started moves from nothing (NULL).
     *
     * Starting the later stages (sales at negotiation on marketing won; payment
     * at awaiting and process at received on the client's approval) is the
     * flow's job, which is not built yet; see docs/database/regray-erd.md.
     *
     * Expects the inquiry to be locked already, by whoever decided the change
     * is allowed. A change that cancels the inquiry (sales rejected, marketing
     * lost) cancels its open visits in the same transaction.
     */
    public function transition(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $to,
        ?User $by,
    ): Inquiry {
        if (! $to instanceof ($track->enum())) {
            throw new InvalidArgumentException("{$to->value} is not a {$track->value} status.");
        }

        $from = $inquiry->{$track->column()};
        $now = Date::now();

        $this->inquiryRepository->updateStatus($inquiry, $track, $to, $now);
        $this->inquiryRepository->createStatusHistory($inquiry, $track, $from, $to, $by?->id, $now);

        if ($to === SalesStatus::Rejected || $to === MarketingStatus::Lost) {
            $this->scheduleService->cancelForInquiry($inquiry, $now);
        }

        event(new InquiryStatusChanged($inquiry, $track, $from, $to, $by?->id));

        return $inquiry;
    }
}

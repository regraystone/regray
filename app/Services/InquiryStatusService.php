<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * Every change to an inquiry's status goes through transition(), which writes
 * the status, its history row and the InquiryStatusChanged event together.
 *
 * The event posts an inquiry card (PostInquiryStatusCard): into the inquiry's
 * group chat when it has one, otherwise into the client's Regray Team thread.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then
 * no real inquiry has a group, so every card goes to the Regray Team thread.
 */
class InquiryStatusService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly InquiryActions $inquiryActions,
    ) {}

    /**
     * Cancel the client's own inquiry.
     *
     * Has to run inside a transaction: the row is locked until it ends, so two
     * cancels at once cannot both read `pending` and both write a history row.
     * The one that waits finds `cancelled` and is refused.
     *
     * @throws ModelNotFoundException When the reference is not this client's.
     * @throws ValidationException When the inquiry is no longer open.
     */
    public function cancel(User $client, string $reference): Inquiry
    {
        $inquiry = $this->inquiryRepository->findForClientByReferenceForUpdate($client, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        $cancel = $this->inquiryActions->cancel($inquiry->status);

        // The button table is the rule: where Cancel is not shown, or is shown
        // and locked, the request is refused too.
        if (! $cancel->visible || ! $cancel->enabled) {
            throw ValidationException::withMessages([
                'status' => 'Only pending or quoted inquiries can be cancelled.',
            ]);
        }

        return $this->transition($inquiry, InquiryStatus::Cancelled, $client);
    }

    /**
     * Move an inquiry to a new status: the status and when it changed, a history
     * row saying who changed it, and the event that posts the status card once
     * the transaction commits.
     *
     * Expects the inquiry to be locked already, by whoever decided the change
     * is allowed.
     */
    public function transition(Inquiry $inquiry, InquiryStatus $to, ?User $by): Inquiry
    {
        $from = $inquiry->status;
        $now = Date::now();

        $this->inquiryRepository->updateStatus($inquiry, $to, $now);
        $this->inquiryRepository->createStatusHistory($inquiry, $from, $to, $by?->id, $now);

        event(new InquiryStatusChanged($inquiry, $from, $to, $by?->id));

        return $inquiry;
    }
}

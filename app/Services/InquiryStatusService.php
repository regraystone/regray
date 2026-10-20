<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

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

        $from = $inquiry->status;
        $now = Date::now();

        $this->inquiryRepository->updateStatus($inquiry, InquiryStatus::Cancelled, $now);
        $this->inquiryRepository->createStatusHistory($inquiry, $from, InquiryStatus::Cancelled, $client->id, $now);

        return $inquiry;
    }
}

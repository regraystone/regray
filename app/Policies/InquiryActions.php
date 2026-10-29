<?php

namespace App\Policies;

use App\Enums\InquiryStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;

/**
 * Which buttons an inquiry shows a client. The one place the table lives: the
 * list sends it to the page and the cancel and update endpoints read the same
 * answer, so the page and the server cannot disagree about what is allowed.
 *
 * Read from the display status, and from whether the client has approved the
 * quotation (sales won), since an on-hold inquiry can be either side of it.
 *
 * | Inquiry                                 | View details | Update    | Cancel       | Get invoice  |
 * |-----------------------------------------|--------------|-----------|--------------|--------------|
 * | not yet approved (pending, negotiation, | clickable    | clickable | clickable    | not rendered |
 * | site_visited, sales on_hold, quoted)    |              |           |              |              |
 * | approved (won, any logistics stage)     | clickable    | disabled  | disabled     | clickable    |
 * | closed                                  | clickable    | not shown | not rendered | clickable    |
 * | cancelled                               | clickable    | disabled  | not rendered | not rendered |
 *
 * Once the client has approved the quotation they can no longer cancel: only
 * sales can, by rejecting, which shows the inquiry as cancelled.
 */
class InquiryActions
{
    public const UPDATE_LOCKED_WHEN_QUOTED = 'Locked once you approved the quotation. Message the team to request changes.';

    public const UPDATE_LOCKED_WHEN_CANCELLED = 'This inquiry was cancelled.';

    public const CANCEL_LOCKED_WHEN_QUOTED = 'You approved the quotation, so only the team can cancel now. Message the team.';

    /** Said when Update is not shown at all (closed) but is asked for anyway. */
    public const UPDATE_NOT_AVAILABLE = 'This inquiry can no longer be changed.';

    public function view(Inquiry $inquiry): InquiryActionState
    {
        return InquiryActionState::enabled();
    }

    public function update(Inquiry $inquiry): InquiryActionState
    {
        return match (true) {
            $inquiry->status === InquiryStatus::Cancelled => InquiryActionState::disabled(self::UPDATE_LOCKED_WHEN_CANCELLED),
            $inquiry->status === InquiryStatus::Closed => InquiryActionState::hidden(),
            $this->isApproved($inquiry) => InquiryActionState::disabled(self::UPDATE_LOCKED_WHEN_QUOTED),
            default => InquiryActionState::enabled(),
        };
    }

    /**
     * Why this inquiry cannot be updated, or null when it can.
     *
     * The edit page and the update endpoint both ask this, so a status that
     * hides the button still gets a sentence rather than a bare refusal.
     */
    public function updateRefusal(Inquiry $inquiry): ?string
    {
        $update = $this->update($inquiry);

        if ($update->enabled) {
            return null;
        }

        return $update->reason ?? self::UPDATE_NOT_AVAILABLE;
    }

    public function cancel(Inquiry $inquiry): InquiryActionState
    {
        return match (true) {
            $inquiry->status === InquiryStatus::Closed, $inquiry->status === InquiryStatus::Cancelled => InquiryActionState::hidden(),
            $this->isApproved($inquiry) => InquiryActionState::disabled(self::CANCEL_LOCKED_WHEN_QUOTED),
            default => InquiryActionState::enabled(),
        };
    }

    public function invoice(Inquiry $inquiry): InquiryActionState
    {
        return match (true) {
            $inquiry->status === InquiryStatus::Cancelled => InquiryActionState::hidden(),
            $inquiry->status === InquiryStatus::Closed, $this->isApproved($inquiry) => InquiryActionState::enabled(),
            default => InquiryActionState::hidden(),
        };
    }

    /**
     * Every button for an inquiry, keyed the way the page reads them.
     *
     * @return array{view: array{visible: bool, enabled: bool, reason?: string}, update: array{visible: bool, enabled: bool, reason?: string}, cancel: array{visible: bool, enabled: bool, reason?: string}, invoice: array{visible: bool, enabled: bool, reason?: string}}
     */
    public function for(Inquiry $inquiry): array
    {
        return [
            'view' => $this->view($inquiry)->toArray(),
            'update' => $this->update($inquiry)->toArray(),
            'cancel' => $this->cancel($inquiry)->toArray(),
            'invoice' => $this->invoice($inquiry)->toArray(),
        ];
    }

    /** Whether the client has approved the quotation. */
    private function isApproved(Inquiry $inquiry): bool
    {
        return $inquiry->sales_status === SalesStatus::Won;
    }
}

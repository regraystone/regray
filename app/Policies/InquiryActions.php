<?php

namespace App\Policies;

use App\Enums\InquiryStatus;

/**
 * Which buttons an inquiry shows a client, by status. The one place the table
 * lives: the list sends it to the page and the cancel endpoint reads the same
 * answer, so the page and the server cannot disagree about what is allowed.
 *
 * | Status    | View details | Update   | Cancel       | Get invoice  |
 * |-----------|--------------|----------|--------------|--------------|
 * | pending   | clickable    | clickable| clickable    | not rendered |
 * | quoted    | clickable    | disabled | clickable    | not rendered |
 * | paid      | clickable    | not shown| not rendered | clickable    |
 * | completed | clickable    | not shown| not rendered | clickable    |
 * | cancelled | clickable    | disabled | not rendered | not rendered |
 */
class InquiryActions
{
    public const UPDATE_LOCKED_WHEN_QUOTED = 'Locked once quoted. Message the team to request changes.';

    public const UPDATE_LOCKED_WHEN_CANCELLED = 'This inquiry was cancelled.';

    /** Said when Update is not shown at all (paid, completed) but is asked for anyway. */
    public const UPDATE_NOT_AVAILABLE = 'This inquiry can no longer be changed.';

    public function view(InquiryStatus $status): InquiryActionState
    {
        return InquiryActionState::enabled();
    }

    public function update(InquiryStatus $status): InquiryActionState
    {
        return match ($status) {
            InquiryStatus::Pending => InquiryActionState::enabled(),
            InquiryStatus::Quoted => InquiryActionState::disabled(self::UPDATE_LOCKED_WHEN_QUOTED),
            InquiryStatus::Cancelled => InquiryActionState::disabled(self::UPDATE_LOCKED_WHEN_CANCELLED),
            default => InquiryActionState::hidden(),
        };
    }

    /**
     * Why an inquiry in this status cannot be updated, or null when it can.
     *
     * The edit page and the update endpoint both ask this, so a status that
     * hides the button still gets a sentence rather than a bare refusal.
     */
    public function updateRefusal(InquiryStatus $status): ?string
    {
        $update = $this->update($status);

        if ($update->enabled) {
            return null;
        }

        return $update->reason ?? self::UPDATE_NOT_AVAILABLE;
    }

    public function cancel(InquiryStatus $status): InquiryActionState
    {
        return match ($status) {
            InquiryStatus::Pending, InquiryStatus::Quoted => InquiryActionState::enabled(),
            default => InquiryActionState::hidden(),
        };
    }

    public function invoice(InquiryStatus $status): InquiryActionState
    {
        return match ($status) {
            InquiryStatus::Paid, InquiryStatus::Completed => InquiryActionState::enabled(),
            default => InquiryActionState::hidden(),
        };
    }

    /**
     * Every button for a status, keyed the way the page reads them.
     *
     * @return array{view: array{visible: bool, enabled: bool, reason?: string}, update: array{visible: bool, enabled: bool, reason?: string}, cancel: array{visible: bool, enabled: bool, reason?: string}, invoice: array{visible: bool, enabled: bool, reason?: string}}
     */
    public function for(InquiryStatus $status): array
    {
        return [
            'view' => $this->view($status)->toArray(),
            'update' => $this->update($status)->toArray(),
            'cancel' => $this->cancel($status)->toArray(),
            'invoice' => $this->invoice($status)->toArray(),
        ];
    }
}

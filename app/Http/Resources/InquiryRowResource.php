<?php

namespace App\Http\Resources;

use App\Enums\DueStatus;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\ClientService;
use App\Services\InquiryStatusRules;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * An inquiry as a row of the Inquiry list: the same columns for every staff
 * role, and the actions this reader has on it. Each action is the policy check
 * its route makes (InquiryPolicy), and, for edit and the status tracks, whether
 * the inquiry's state still allows it.
 *
 * Expects the client record, its contacts, the account and the schedule loaded
 * (InquiryRepositoryInterface::paginateForStaff()).
 *
 * `deadline` is the date its schedule says the project is needed by,
 * `completedAt` the date the project was completed, and `due` how the inquiry
 * stands against the deadline (DueStatus): while it is open by today, and once
 * completed by the day it was completed. `due` is null without a schedule and
 * for a cancelled inquiry, which has nothing left to be late for.
 *
 * @mixin Inquiry
 */
class InquiryRowResource extends JsonResource
{
    /**
     * @return array{reference: string, createdAt: string, client: string, contact: string|null, status: string, salesStatus: string|null, hasBeenQuoted: bool, paymentStatus: string|null, processStatus: string|null, deadline: string|null, completedAt: string|null, due: string|null, actions: list<string>}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return [
            'reference' => $this->reference,
            'createdAt' => $this->created_at->toIso8601String(),
            'client' => $this->clientRecord->full_name ?? $this->client->name ?? '—',
            'contact' => ClientService::phone($this->clientRecord, $this->client),
            'status' => $this->status->value,
            'salesStatus' => $this->sales_status?->value,
            // Whether a quotation was ever sent, which opens sales' revision and won.
            'hasBeenQuoted' => $this->quote_revision !== null,
            'paymentStatus' => $this->payment_status?->value,
            'processStatus' => $this->process_status?->value,
            'deadline' => $this->schedule?->arrival_at->setTimezone(ScheduleService::timezone())->toDateString(),
            'completedAt' => $this->completed_at?->setTimezone(ScheduleService::timezone())->toDateString(),
            'due' => $this->due()?->value,
            'actions' => $this->actionsFor($viewer),
        ];
    }

    private function due(): ?DueStatus
    {
        if ($this->schedule === null || $this->status === InquiryStatus::Cancelled) {
            return null;
        }

        if ($this->status === InquiryStatus::Closed && $this->completed_at === null) {
            return null;
        }

        return DueStatus::forDeadline($this->schedule->arrival_at, now(), ScheduleService::timezone(), $this->completed_at);
    }

    /** @return list<string> */
    private function actionsFor(User $viewer): array
    {
        $actions = [];
        $inquiry = $this->resource;

        if (Gate::forUser($viewer)->allows('view', $inquiry)) {
            $actions[] = 'view';
        }

        if (Gate::forUser($viewer)->allows('update', $inquiry) && InquiryStatusRules::isEditableBy($inquiry, $viewer)) {
            $actions[] = 'edit';
        }

        foreach (InquiryStatusTrack::cases() as $track) {
            if (Gate::forUser($viewer)->allows('updateStatus', [$inquiry, $track]) && InquiryStatusRules::canMove($inquiry, $track)) {
                $actions[] = 'status.'.$track->value;
            }
        }

        if (Gate::forUser($viewer)->allows('uploadJobOrder', $inquiry) && InquiryStatusRules::canUploadJobOrder($inquiry)) {
            $actions[] = 'job_order';
        }

        return $actions;
    }
}

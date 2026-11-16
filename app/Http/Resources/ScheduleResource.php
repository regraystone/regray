<?php

namespace App\Http\Resources;

use App\Enums\BookedByType;
use App\Enums\ScheduleAction;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A visit on the Schedule page.
 *
 * Nothing about the client for a client reader: they only ever see their own,
 * and read awaiting sales as pending (ScheduleStatus::forClient()), with
 * nothing of who it is assigned to or who declined it. Staff are told whose
 * appointment it is (`clientName`), who booked it when staff did, the sales
 * member it is assigned to, and the last decline while it waits to be
 * assigned again. No contact details and no ids of other people for anyone.
 *
 * `can` says which changes this reader may make to it now: SchedulePolicy for
 * who may, and ScheduleService::refusalFor() for whether its state still
 * allows it, so the page never offers a button whose save would be refused.
 *
 * @mixin Schedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, arrivalAt: string, status: string, notes: string|null, inquiryReference: string|null, bookedByType: string, bookedByName: string|null, clientName: string|null, assignedSalesName: string|null, declinedByName: string|null, declineReason: string|null, cancelReason: string|null, changeReason: string|null, can: array<string, bool>}
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer No one outside a request, as in a test or a queued job. */
        $viewer = $request->user();
        $isStaffReader = $viewer !== null && ! $viewer->isClient();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'arrivalAt' => $this->arrival_at->toIso8601String(),
            'status' => $this->status->for($viewer)->value,
            'notes' => $this->notes,
            'inquiryReference' => $this->inquiry?->reference,
            'bookedByType' => $this->booked_by_type->value,
            'bookedByName' => $this->booked_by_type === BookedByType::Staff ? $this->bookedBy?->name : null,
            'clientName' => $isStaffReader ? $this->clientName() : null,
            'assignedSalesName' => $isStaffReader ? $this->assignee?->name : null,
            'declinedByName' => $isStaffReader && $this->declined_at !== null ? $this->declinedBy?->name : null,
            'declineReason' => $isStaffReader ? $this->decline_reason : null,
            'cancelReason' => $this->cancel_reason,
            'changeReason' => $this->change_reason,
            'can' => $this->abilities($viewer),
        ];
    }

    /** @return array<string, bool> */
    private function abilities(?User $viewer): array
    {
        $scheduleService = app(ScheduleService::class);
        $abilities = [];

        foreach (ScheduleAction::cases() as $action) {
            // The state first: it asks the database nothing, and refuses an inquiry's visit before the policy reads its inquiry.
            $abilities[$action->value] = $viewer !== null
                && $scheduleService->refusalFor($this->resource, $action) === null
                && Gate::forUser($viewer)->allows($action->value, $this->resource);
        }

        return $abilities;
    }
}

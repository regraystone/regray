<?php

namespace App\Http\Resources;

use App\Enums\InquiryStatusTrack;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\ClientService;
use App\Services\InquiryStatusRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * An inquiry as a row of the Inquiry list: the same columns for every staff
 * role, and the actions this reader has on it. Each action is the policy check
 * its route makes (InquiryPolicy), and, for edit and the status tracks, whether
 * the inquiry's state still allows it.
 *
 * Expects the client record, its contacts and the account loaded
 * (InquiryRepositoryInterface::paginateForStaff()).
 *
 * @mixin Inquiry
 */
class InquiryRowResource extends JsonResource
{
    /**
     * @return array{reference: string, createdAt: string, client: string, contact: string|null, status: string, salesStatus: string|null, paymentStatus: string|null, processStatus: string|null, actions: list<string>}
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
            'paymentStatus' => $this->payment_status?->value,
            'processStatus' => $this->process_status?->value,
            'actions' => $this->actionsFor($viewer),
        ];
    }

    /** @return list<string> */
    private function actionsFor(User $viewer): array
    {
        $actions = [];
        $inquiry = $this->resource;

        if (Gate::forUser($viewer)->allows('view', $inquiry)) {
            $actions[] = 'view';
        }

        if (Gate::forUser($viewer)->allows('update', $inquiry) && InquiryStatusRules::isOpen($inquiry)) {
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

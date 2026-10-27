<?php

namespace App\Http\Resources\Message;

use App\Models\InquiryProject;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project as a card in a thread: which project of which inquiry, and the
 * snapshot the card announces. Built from the `project_card` message, so the
 * three statuses are the ones the card was posted for, not whatever the
 * project, its quotation and its payment are now.
 *
 * Not to be confused with App\Http\Resources\ProjectCardResource, which is a
 * portfolio project on the Projects page.
 *
 * @mixin Message
 */
class ProjectCardResource extends JsonResource
{
    /**
     * @return array{reference: string, name: string, location: string, projectStatus: string, quotationStatus: string, paymentStatus: string, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var InquiryProject $project */
        $project = $this->inquiryProject;

        return [
            'reference' => $project->inquiry->reference,
            'name' => $project->name,
            'location' => $project->location,
            'projectStatus' => (string) $this->announced_status,
            'quotationStatus' => (string) $this->announced_quotation_status,
            'paymentStatus' => (string) $this->announced_payment_status,
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}

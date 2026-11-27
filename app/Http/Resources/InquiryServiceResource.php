<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\StoneType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Inquire page's second dropdown, with what is shown under it,
 * the slugs of the stone types it is made from (a stone fits the service when
 * its primary type is one of them), whether it asks for stones at all, how it
 * reaches the client (`fulfillment`: `delivery_or_pickup` asks Delivery or
 * Pickup, `installed` only the installation address), and the questions it
 * asks besides them, in order (InquiryDetailFieldResource). `deliveryNote`
 * is the line under its delivery method, `scheduleNote` the line under its
 * date, and `leadDays` the fewest days from today its date may be (0: today).
 *
 * A service with an installation asks which application (`hasApplications`)
 * and lists them (`applications`), each in this same shape: a project then
 * asks each stone the service's questions and the project the
 * application's, from the application's stone types (withApplication in
 * inquiry-flow.ts). The catalogue hands them in (InquiryServiceTypeResource);
 * a held service (an edit) reads its type's.
 *
 * @mixin Service
 */
class InquiryServiceResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, description: string, features: list<string>, stoneTypes: list<string>, requiresStones: bool, fulfillment: string, deliveryNote: string|null, scheduleNote: string|null, leadDays: int, fields: array<int, mixed>, hasApplications: bool, applications: array<int, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'features' => array_values($this->features->map(fn (ServiceFeature $feature): string => $feature->text)->all()),
            'stoneTypes' => array_values($this->stoneTypes->map(fn (StoneType $type): string => $type->slug)->all()),
            'requiresStones' => $this->requires_stones,
            'fulfillment' => $this->fulfillment->value,
            'deliveryNote' => $this->delivery_note,
            'scheduleNote' => $this->schedule_note,
            'leadDays' => (int) $this->lead_days,
            'fields' => InquiryDetailFieldResource::collection($this->detailFields)->resolve($request),
            'hasApplications' => $this->has_applications,
            'applications' => $this->has_applications
                ? InquiryServiceResource::collection($this->applications)->resolve($request)
                : [],
        ];
    }
}

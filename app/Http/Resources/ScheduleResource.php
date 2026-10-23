<?php

namespace App\Http\Resources;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visit on the Schedule page. Nothing about the client: no name, no contact
 * details, no ids of other people.
 *
 * @mixin Schedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, arrivalAt: string, status: string, notes: string|null, inquiryReference: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'arrivalAt' => $this->arrival_at->toIso8601String(),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'inquiryReference' => $this->inquiry?->reference,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A service as a row of the admin's All services page, and what its View
 * drawer and Edit form read.
 *
 * `actions` and `canToggle` are what this reader may do to it, from the
 * permission each route checks. `isOffered` is whether the Inquire page
 * offers it now: visible, its type visible, and made from a stone type when
 * it needs stones.
 *
 * Expects ServiceRepositoryInterface::paginateForAdmin()'s relations and count.
 *
 * @mixin Service
 */
class AdminServiceResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, name: string, serviceTypeId: int, description: string, requiresStones: bool, fulfillment: string, deliveryNote: string|null, scheduleNote: string|null, leadDays: int|null, isActive: bool, isOffered: bool, sortOrder: int, features: list<string>, stoneTypes: list<array{id: int, name: string}>, questions: list<array{id: int, name: string, scope: string, required: bool}>, inquiriesCount: int, createdAt: string, updatedAt: string, actions: list<string>, canToggle: bool}
     */
    public function toArray(Request $request): array
    {
        $gate = Gate::forUser($request->user());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'serviceTypeId' => $this->service_type_id,
            'description' => $this->description,
            'requiresStones' => $this->requires_stones,
            'fulfillment' => $this->fulfillment->value,
            'deliveryNote' => $this->delivery_note,
            'scheduleNote' => $this->schedule_note,
            'leadDays' => $this->lead_days,
            'isActive' => $this->is_active,
            'isOffered' => $this->isOffered(),
            'sortOrder' => $this->sort_order,
            'features' => $this->featureLines(),
            'stoneTypes' => $this->stoneTypeOptions(),
            'questions' => $this->questionRows(),
            'inquiriesCount' => (int) ($this->inquiries_count ?? 0),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'actions' => array_values(array_filter([
                'view',
                $gate->allows(Permission::ServicesUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::ServicesArchive->value) ? 'archive' : null,
            ])),
            'canToggle' => $gate->allows(Permission::ServicesUpdate->value),
        ];
    }

    /** @return list<string> */
    private function featureLines(): array
    {
        $lines = [];

        foreach ($this->features as $feature) {
            $lines[] = $feature->text;
        }

        return $lines;
    }

    /** @return list<array{id: int, name: string}> */
    private function stoneTypeOptions(): array
    {
        $types = [];

        foreach ($this->stoneTypes as $type) {
            $types[] = ['id' => $type->id, 'name' => $type->name];
        }

        return $types;
    }

    /** @return list<array{id: int, name: string, scope: string, required: bool}> */
    private function questionRows(): array
    {
        $questions = [];

        foreach ($this->detailFields as $field) {
            $questions[] = [
                'id' => $field->id,
                'name' => $field->name,
                'scope' => $field->scope->value,
                'required' => $field->isRequiredByService(),
            ];
        }

        return $questions;
    }
}

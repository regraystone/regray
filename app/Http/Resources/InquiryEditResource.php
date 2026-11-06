<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStone;
use App\Models\InquiryWorkItem;
use App\Models\Service;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as the Inquire page's edit mode is filled from: the one project
 * it is, in the shape of a project on that page.
 *
 * Unlike InquiryDetailsResource, which is for reading, every identifier here is
 * the one the form sends back: the inquiry's and the files' real ids, service,
 * furniture type and stone slugs, and edge and finish names. Only the client's
 * own attachments, nothing about the client and no file paths.
 *
 * `heldServices` are the services it already has, so one no longer offered to new
 * inquiries still reads back on the page and can be kept.
 *
 * @mixin Inquiry
 */
class InquiryEditResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, work: list<array{stoneId: string|null, serviceId: string, furnitureType: string|null}>, heldServices: array<int, mixed>, stoneAdviceRequested: bool, stones: list<array{stoneId: string, quantity: int, edge: string, finish: string}>, deliveryMethod: string, location: string|null, landmark: string|null, areaSqm: float|null, notes: string|null, neededBy: string|null, deadlineLocked: bool, deadlineLockedReason: string|null, attachments: list<array{id: int, name: string, sizeBytes: int, mimeType: string, status: string}>}
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->schedule;
        $arrivalAt = $schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $lockedReason = $schedule === null ? null : app(ScheduleService::class)->deadlineLockedReason($schedule);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'work' => array_values($this->workItems->map(fn (InquiryWorkItem $item): array => [
                'stoneId' => $item->stone?->slug,
                'serviceId' => $item->service->slug,
                'furnitureType' => $item->furnitureType?->slug,
            ])->all()),
            'heldServices' => InquiryServiceResource::collection(
                $this->workItems
                    ->map(fn (InquiryWorkItem $item): Service => $item->service)
                    ->unique('id')
                    ->values(),
            )->resolve($request),
            'stoneAdviceRequested' => $this->stone_advice_requested,
            'stones' => array_values($this->stones->map(fn (InquiryStone $row): array => [
                'stoneId' => $row->stone->slug,
                'quantity' => $row->quantity,
                'edge' => $row->edgeProfile->name,
                'finish' => $row->finish->name,
            ])->all()),
            'deliveryMethod' => $this->delivery_method->value,
            'location' => $this->location,
            'landmark' => $this->landmark,
            'areaSqm' => $this->area_sqm === null ? null : (float) $this->area_sqm,
            'notes' => $this->notes,
            'neededBy' => $arrivalAt?->toDateString(),
            'deadlineLocked' => $lockedReason !== null,
            'deadlineLockedReason' => $lockedReason,
            'attachments' => array_values($this->files
                ->filter(fn (InquiryFile $file): bool => $file->document_type === DocumentType::ClientAttachment)
                ->map(fn (InquiryFile $file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'sizeBytes' => $file->size_bytes,
                    'mimeType' => $file->mime_type,
                    'status' => 'saved',
                ])->all()),
        ];
    }
}

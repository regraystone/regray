<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStone;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as the Inquire page's edit mode is filled from: the one project
 * it is, in the shape of a project on that page.
 *
 * Unlike InquiryDetailsResource, which is for reading, every identifier here is
 * the one the form sends back: the inquiry's and the files' real ids, service
 * and application slugs, stone slugs, and edge and finish names. Only the
 * client's own attachments, nothing about the client and no file paths.
 *
 * @mixin Inquiry
 */
class InquiryEditResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, serviceSlugs: list<string>, serviceOptionSlugs: array<string, string>, applicationSlugs: list<string>, stoneAdviceRequested: bool, stones: list<array{stoneId: string, quantity: int, edge: string, finish: string}>, location: string, landmark: string|null, areaSqm: float|null, notes: string|null, neededBy: string|null, neededByTime: string|null, deadlineLocked: bool, deadlineLockedReason: string|null, attachments: list<array{id: int, name: string, sizeBytes: int, mimeType: string, status: string}>}
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->schedule;
        $arrivalAt = $schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $isVisit = $this->services->isNotEmpty() && $this->services->every(fn (Service $service): bool => $service->is_standalone);
        $lockedReason = $schedule === null ? null : app(ScheduleService::class)->deadlineLockedReason($schedule);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'serviceSlugs' => array_values($this->services->map(fn (Service $service): string => $service->slug)->all()),
            // The kind of work picked under each service, by service slug. A
            // service sent before the choice existed is left out, to be picked.
            'serviceOptionSlugs' => $this->services
                ->mapWithKeys(fn (Service $service): array => [
                    $service->slug => $this->serviceOptions->firstWhere('service_id', $service->id)?->slug,
                ])
                ->filter()
                ->all(),
            'applicationSlugs' => array_values($this->categories->map(fn (ProjectCategory $category): string => $category->slug)->all()),
            'stoneAdviceRequested' => $this->stone_advice_requested,
            'stones' => array_values($this->stones->map(fn (InquiryStone $row): array => [
                'stoneId' => $row->stone->slug,
                'quantity' => $row->quantity,
                'edge' => $row->edgeProfile->name,
                'finish' => $row->finish->name,
            ])->all()),
            'location' => $this->location,
            'landmark' => $this->landmark,
            'areaSqm' => $this->area_sqm === null ? null : (float) $this->area_sqm,
            'notes' => $this->notes,
            'neededBy' => $arrivalAt?->toDateString(),
            // Only a visit asks for a time; any other project's schedule holds
            // the default time, which the client never typed.
            'neededByTime' => $isVisit ? $arrivalAt?->format('H:i') : null,
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

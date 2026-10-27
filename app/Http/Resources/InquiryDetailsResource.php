<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\InquiryStone;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as its client sees it: the one project it is, the status it is
 * shown at, its four role statuses, the history of how they moved, and the
 * documents the team has shared with the client. Names,
 * quantities and dates: no database ids, nothing about who changed a status or
 * who handles the inquiry, and no file paths or disks.
 *
 * @mixin Inquiry
 */
class InquiryDetailsResource extends JsonResource
{
    /** @var array{id: int, name: string}|null */
    private ?array $conversationLink = null;

    /**
     * The inquiry's group chat, when the viewer is in it.
     *
     * TODO(inquiry-binding): groups will be bound to inquiries later. Until
     * then no group is linked to an inquiry, so this is always null.
     *
     * @param  array{id: int, name: string}|null  $conversation
     */
    public function withConversation(?array $conversation): static
    {
        $this->conversationLink = $conversation;

        return $this;
    }

    /**
     * @return array{reference: string, status: string, marketingStatus: string, salesStatus: string|null, paymentStatus: string|null, processStatus: string|null, createdAt: string, history: list<array{track: string, status: string, at: string}>, documents: list<array{id: string, type: string, revision: int|null, name: string, sizeBytes: int, mimeType: string, at: string}>, project: array<string, mixed>, conversation: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'marketingStatus' => $this->marketing_status->value,
            // Null until the role's stage has started.
            'salesStatus' => $this->sales_status?->value,
            'paymentStatus' => $this->payment_status?->value,
            'processStatus' => $this->process_status?->value,
            'createdAt' => $this->created_at->toIso8601String(),
            'history' => $this->history(),
            'documents' => $this->documents(),
            'project' => $this->project(),
            'conversation' => $this->conversationLink,
        ];
    }

    /**
     * The client's activity log: every change to sales, payment and logistics,
     * oldest first, a stage starting included. Marketing is the team's own
     * business and is left out, except a cancel (marketing lost), which the
     * client has to see. Who made a change is not sent either.
     *
     * @return list<array{track: string, status: string, at: string}>
     */
    private function history(): array
    {
        return array_values($this->statusHistory
            ->reject(fn (InquiryStatusHistory $change): bool => $change->track === InquiryStatusTrack::Marketing && $change->to_status !== MarketingStatus::Lost->value)
            ->map(fn (InquiryStatusHistory $change): array => [
                'track' => $change->track->value,
                'status' => $change->to_status,
                'at' => $change->changed_at->toIso8601String(),
            ])
            ->all());
    }

    /**
     * The documents the team has shared with the client, oldest first: each
     * quotation issued (with its revision) and each payment receipt. The job
     * order is internal and is never sent, and neither are paths or disks.
     *
     * @return list<array{id: string, type: string, revision: int|null, name: string, sizeBytes: int, mimeType: string, at: string}>
     */
    private function documents(): array
    {
        return array_values($this->files
            ->filter(fn (InquiryFile $file): bool => in_array($file->document_type, [DocumentType::Quotation, DocumentType::Receipt], true))
            ->sortBy(fn (InquiryFile $file): string => $file->created_at->format('U.u').'-'.str_pad((string) $file->id, 12, '0', STR_PAD_LEFT))
            ->values()
            ->map(fn (InquiryFile $file, int $position): array => [
                // A key for the page's list, not a database id.
                'id' => 'document-'.($position + 1),
                'type' => $file->document_type->value,
                'revision' => $file->revision,
                'name' => $file->original_name,
                'sizeBytes' => $file->size_bytes,
                'mimeType' => $file->mime_type,
                'at' => $file->created_at->toIso8601String(),
            ])
            ->all());
    }

    /**
     * The target deadline is the arrival of the inquiry's schedule, in the
     * studio's zone, so it reads back as the date and time the client typed.
     * The files are the client's own attachments; staff documents are not shown.
     *
     * @return array<string, mixed>
     */
    private function project(): array
    {
        $arrivalAt = $this->schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $isVisit = $this->services->isNotEmpty() && $this->services->every(fn ($service): bool => $service->is_standalone);

        return [
            'name' => $this->name,
            'services' => $this->services->map(fn ($service): array => [
                'name' => $service->name,
                'image' => ProjectCardResource::resolveThumbnailUrl($service->image_path),
            ])->values()->all(),
            'applications' => $this->categories->map(fn ($category): string => $category->name)->values()->all(),
            'stones' => $this->stones->map(fn (InquiryStone $row): array => [
                'id' => $row->stone->slug,
                'name' => $row->stone->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                'quantity' => $row->quantity,
                'edge' => $row->edgeProfile->name,
                'finish' => $row->finish->name,
            ])->values()->all(),
            'requirements' => [
                'location' => $this->location,
                'landmark' => $this->landmark,
                'areaSqm' => $this->area_sqm === null ? null : (float) $this->area_sqm,
                'neededBy' => $arrivalAt?->toDateString(),
                // Only a visit asks for a time; any other project's schedule
                // holds the default time, which the client never typed.
                'neededByTime' => $isVisit ? $arrivalAt?->format('H:i') : null,
                'notes' => $this->notes,
                'attachments' => $this->files
                    ->filter(fn (InquiryFile $file): bool => $file->document_type === DocumentType::ClientAttachment)
                    ->values()
                    ->map(fn (InquiryFile $file, int $position): array => [
                        'id' => 'file-'.($position + 1),
                        'name' => $file->original_name,
                        'sizeBytes' => $file->size_bytes,
                        'mimeType' => $file->mime_type,
                    ])->all(),
            ],
        ];
    }
}

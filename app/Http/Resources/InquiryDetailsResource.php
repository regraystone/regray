<?php

namespace App\Http\Resources;

use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryProjectStone;
use App\Models\InquiryStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as its client sees it. Names, quantities and dates: no database
 * ids, nothing about who changed a status or who handles the inquiry, and no
 * file paths or disks.
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
     * @return array{reference: string, status: string, createdAt: string, updatedAt: string, services: list<string>, statusHistory: list<array{status: string, at: string}>, projects: list<array<string, mixed>>, conversation: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        $services = [];

        foreach ($this->projects as $project) {
            foreach ($project->services as $service) {
                $services[$service->name] = true;
            }
        }

        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->status_changed_at->toIso8601String(),
            'services' => array_keys($services),
            'statusHistory' => array_values($this->statusHistory->map(fn (InquiryStatusHistory $change): array => [
                'status' => $change->to_status->value,
                'at' => $change->changed_at->toIso8601String(),
            ])->all()),
            'projects' => array_values($this->projects->map(fn (InquiryProject $project): array => $this->project($project))->all()),
            'conversation' => $this->conversationLink,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function project(InquiryProject $project): array
    {
        return [
            // A key for the page's list, not a database id.
            'id' => 'project-'.$project->sort_order,
            'name' => $project->name,
            'services' => $project->services->map(fn ($service): array => [
                'name' => $service->name,
                'image' => ProjectCardResource::resolveThumbnailUrl($service->image_path),
            ])->values()->all(),
            'applications' => $project->categories->map(fn ($category): string => $category->name)->values()->all(),
            'stones' => $project->stones->map(fn (InquiryProjectStone $row): array => [
                'id' => $row->stone->slug,
                'name' => $row->stone->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                'quantity' => $row->quantity,
                'edge' => $row->edgeProfile->name,
                'finish' => $row->finish->name,
            ])->values()->all(),
            'requirements' => [
                'location' => $project->location,
                'landmark' => $project->landmark,
                'areaSqm' => $project->area_sqm === null ? null : (float) $project->area_sqm,
                'neededBy' => $project->deadline_date,
                'neededByTime' => $project->deadline_time === null ? null : substr($project->deadline_time, 0, 5),
                'notes' => $project->notes,
                'attachments' => $project->files->values()->map(fn (InquiryFile $file, int $position): array => [
                    'id' => 'file-'.($position + 1),
                    'name' => $file->original_name,
                    'sizeBytes' => $file->size_bytes,
                    'mimeType' => $file->mime_type,
                ])->all(),
            ],
        ];
    }
}

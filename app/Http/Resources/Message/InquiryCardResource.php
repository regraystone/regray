<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\ProjectCardResource;
use App\Models\Inquiry;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as a card in a thread: what it is, which stones it is about, and
 * the status the card announces. Built from the `inquiry_card` message, so the
 * status is the one the card was posted for, not whatever the inquiry is now.
 *
 * @mixin Message
 */
class InquiryCardResource extends JsonResource
{
    /** How many stone pictures the card draws; the rest are counted. */
    public const HERO_STONES = 2;

    /**
     * @return array{reference: string, projectCount: int, serviceCount: int, stones: list<array{slug: string, name: string, thumbnail: string}>, stoneCount: int, announcedStatus: string, status: string, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->inquiry;
        $projects = $inquiry->projects->sortBy('sort_order')->values();

        $stones = [];
        $services = [];

        foreach ($projects as $project) {
            foreach ($project->services as $service) {
                $services[$service->id] = true;
            }

            foreach ($project->stones->sortBy('sort_order') as $row) {
                if ($row->stone !== null && ! isset($stones[$row->stone->slug])) {
                    $stones[$row->stone->slug] = [
                        'slug' => $row->stone->slug,
                        'name' => $row->stone->name,
                        'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                    ];
                }
            }
        }

        return [
            'reference' => $inquiry->reference,
            'projectCount' => $projects->count(),
            'serviceCount' => count($services),
            'stones' => array_slice(array_values($stones), 0, self::HERO_STONES),
            'stoneCount' => count($stones),
            'announcedStatus' => (string) $this->announced_status,
            'status' => $inquiry->status->value,
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}

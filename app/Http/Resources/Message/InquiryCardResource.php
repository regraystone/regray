<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\ProjectCardResource;
use App\Models\Inquiry;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as a card in a thread: which project it is, which stones it is
 * about, and the snapshot the card announces. Built from the `inquiry_card`
 * message, so the statuses are the ones the card was posted for, not whatever
 * the inquiry is now. The headline is read from the announced display status.
 *
 * @mixin Message
 */
class InquiryCardResource extends JsonResource
{
    /** How many stone pictures the card draws; the rest are counted. */
    public const HERO_STONES = 2;

    /**
     * @return array{reference: string, name: string, location: string, stones: list<array{slug: string, name: string, thumbnail: string}>, stoneCount: int, announcedStatus: string, announcedSalesStatus: string|null, announcedPaymentStatus: string|null, announcedProcessStatus: string|null, status: string, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->inquiry;

        $stones = [];

        foreach ($inquiry->stones->sortBy('sort_order') as $row) {
            if ($row->stone !== null && ! isset($stones[$row->stone->slug])) {
                $stones[$row->stone->slug] = [
                    'slug' => $row->stone->slug,
                    'name' => $row->stone->name,
                    'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                ];
            }
        }

        return [
            'reference' => $inquiry->reference,
            'name' => $inquiry->name,
            'location' => $inquiry->location,
            'stones' => array_slice(array_values($stones), 0, self::HERO_STONES),
            'stoneCount' => count($stones),
            'announcedStatus' => (string) $this->announced_status,
            // Null for a stage that had not started when the card was posted.
            'announcedSalesStatus' => $this->announced_sales_status,
            'announcedPaymentStatus' => $this->announced_payment_status,
            'announcedProcessStatus' => $this->announced_process_status,
            'status' => $inquiry->status->value,
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}

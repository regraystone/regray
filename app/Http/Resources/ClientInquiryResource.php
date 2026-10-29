<?php

namespace App\Http\Resources;

use App\Data\ClientInquiryCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry on the Quote page. Only what the card shows: nothing about who
 * sent it, who handled it, or where its files are stored.
 *
 * @mixin ClientInquiryCard
 */
class ClientInquiryResource extends JsonResource
{
    /**
     * @return array{reference: string, status: string, paymentStatus: string|null, createdAt: string, title: string, stones: list<array{slug: string, name: string, thumbnail: string}>, services: list<array{id: string, name: string}>, actions: array<string, array{visible: bool, enabled: bool, reason?: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'paymentStatus' => $this->paymentStatus?->value,
            'createdAt' => $this->createdAt->toIso8601String(),
            'title' => $this->title,
            'stones' => array_map(fn (array $stone): array => [
                'slug' => $stone['slug'],
                'name' => $stone['name'],
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($stone['thumbnail_path']),
            ], $this->stones),
            'services' => $this->services,
            'actions' => $this->actions,
        ];
    }
}

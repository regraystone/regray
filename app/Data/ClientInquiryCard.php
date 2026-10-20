<?php

namespace App\Data;

use App\Enums\InquiryStatus;
use Carbon\CarbonInterface;

/**
 * One inquiry as a card on the Quote page: only what the card shows.
 */
final readonly class ClientInquiryCard
{
    /**
     * @param  list<array{slug: string, name: string, thumbnail_path: string}>  $stones  Unique, first-picked first.
     * @param  list<array{id: string, name: string}>  $services  Unique, first-picked first.
     * @param  array<string, array{visible: bool, enabled: bool, reason?: string}>  $actions  Straight from InquiryActions.
     */
    public function __construct(
        public string $reference,
        public InquiryStatus $status,
        public CarbonInterface $createdAt,
        public string $title,
        public int $projectCount,
        public array $stones,
        public array $services,
        public array $actions,
    ) {}
}

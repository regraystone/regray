<?php

namespace App\Data;

use App\Enums\InquiryStatus;

final readonly class InquiryListFilters
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public function __construct(
        public ?string $search = null,
        public ?InquiryStatus $status = null,
        public string $sort = self::SORT_NEWEST,
        public int $page = 1,
    ) {}
}

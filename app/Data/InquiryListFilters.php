<?php

namespace App\Data;

use App\Enums\InquiryStatusGroup;
use App\Enums\QuoteTab;

final readonly class InquiryListFilters
{
    public const SORT_NEWEST = 'newest';

    public const SORT_OLDEST = 'oldest';

    public function __construct(
        public ?string $search = null,
        /** The Quote list tab; null is all of them. */
        public ?InquiryStatusGroup $status = null,
        public string $sort = self::SORT_NEWEST,
        public int $page = 1,
        /** The Quotations page's tab (Pending, Quoted, Accepted); null is All. */
        public ?QuoteTab $tab = null,
    ) {}
}

<?php

namespace App\Actions\Quote;

use App\Data\ClientInquiryCard;
use App\Data\InquiryListFilters;
use App\Models\User;
use App\Services\ClientInquiryService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListClientInquiriesAction
{
    public function __construct(
        public readonly ClientInquiryService $clientInquiryService,
    ) {}

    /**
     * @return array{inquiries: LengthAwarePaginator<int, ClientInquiryCard>, hasMore: bool}
     */
    public function execute(User $client, InquiryListFilters $filters): array
    {
        return $this->clientInquiryService->listFor($client, $filters);
    }
}

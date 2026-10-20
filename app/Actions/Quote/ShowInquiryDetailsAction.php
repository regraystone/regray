<?php

namespace App\Actions\Quote;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryDetailsService;

class ShowInquiryDetailsAction
{
    public function __construct(
        public readonly InquiryDetailsService $inquiryDetailsService,
    ) {}

    public function execute(User $client, string $reference): Inquiry
    {
        return $this->inquiryDetailsService->forClient($client, $reference);
    }
}

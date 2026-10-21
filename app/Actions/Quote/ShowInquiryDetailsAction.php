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

    /**
     * @return array{inquiry: Inquiry, conversation: array{id: int, name: string}|null}
     */
    public function execute(User $client, string $reference): array
    {
        $inquiry = $this->inquiryDetailsService->forClient($client, $reference);

        return [
            'inquiry' => $inquiry,
            'conversation' => $this->inquiryDetailsService->conversationFor($client, $inquiry),
        ];
    }
}

<?php

namespace App\Actions\Quote;

use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Services\InquiryDetailsService;

class ShowInquiryEditAction
{
    public function __construct(
        public readonly InquiryDetailsService $inquiryDetailsService,
        public readonly InquiryActions $inquiryActions,
    ) {}

    /**
     * The client's own inquiry with everything the edit form is filled from, and
     * why it may not be edited, if it may not.
     *
     * @return array{inquiry: Inquiry, refusal: string|null}
     */
    public function execute(User $client, string $reference): array
    {
        $inquiry = $this->inquiryDetailsService->forClient($client, $reference);

        return [
            'inquiry' => $inquiry,
            'refusal' => $this->inquiryActions->updateRefusal($inquiry),
        ];
    }
}

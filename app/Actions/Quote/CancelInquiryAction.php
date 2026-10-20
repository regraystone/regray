<?php

namespace App\Actions\Quote;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryStatusService;
use Illuminate\Support\Facades\DB;

class CancelInquiryAction
{
    public function __construct(
        public readonly InquiryStatusService $inquiryStatusService,
    ) {}

    /** Owns the transaction the service's row lock lives in. */
    public function execute(User $client, string $reference): Inquiry
    {
        return DB::transaction(fn (): Inquiry => $this->inquiryStatusService->cancel($client, $reference));
    }
}

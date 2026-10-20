<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InquiryDetailsService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
    ) {}

    /**
     * One of this client's inquiries with everything the details page shows.
     *
     * A reference that is not theirs is the same 404 as one that does not exist,
     * so the answer never says which references are in use.
     *
     * @throws ModelNotFoundException
     */
    public function forClient(User $client, string $reference): Inquiry
    {
        return $this->inquiryRepository->findForClientByReferenceWithDetails($client, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);
    }
}

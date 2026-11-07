<?php

namespace App\Actions\Inquiry;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ServiceType;
use App\Services\InquiryCatalogService;
use Illuminate\Database\Eloquent\Collection;

class ListInquiryOptionsAction
{
    public function __construct(
        public readonly InquiryCatalogService $catalogService,
    ) {}

    /**
     * @return array{serviceTypes: Collection<int, ServiceType>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>}
     */
    public function execute(): array
    {
        return $this->catalogService->options();
    }
}

<?php

namespace App\Actions\Inquiry;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Services\InquiryCatalogService;
use Illuminate\Database\Eloquent\Collection;

class ListInquiryOptionsAction
{
    public function __construct(
        public readonly InquiryCatalogService $catalogService,
    ) {}

    /**
     * @return array{services: Collection<int, Service>, stones: Collection<int, Stone>, applications: Collection<int, ProjectCategory>, edgeProfiles: Collection<int, EdgeProfile>, finishes: Collection<int, Finish>, locations: list<string>}
     */
    public function execute(): array
    {
        return $this->catalogService->options();
    }
}

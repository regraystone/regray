<?php

namespace App\Actions\Inquiry;

use App\Data\InquiryStoneFilterData;
use App\Http\Resources\StoneCardResource;
use App\Models\StoneType;
use App\Models\User;
use App\Services\InquiryCatalogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ListInquiryStonesAction
{
    public function __construct(
        public readonly InquiryCatalogService $catalogService,
    ) {}

    /**
     * The Inquire page's props for its stones, read from the server a page at a
     * time like a brand's Our Collection. `pickedStones` is asked for by slug, for a
     * restored draft whose stones may be on no page the reader has reached.
     *
     * @return array<string, mixed>
     */
    public function execute(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $filters = InquiryStoneFilterData::fromRequest($request);

        return [
            'stones' => Inertia::scroll(fn () => StoneCardResource::collection($this->catalogService->stones($user, $filters))),
            'stoneTypes' => Inertia::once(fn () => $this->catalogService->stoneTypes()
                ->map(fn (StoneType $type): array => ['value' => $type->slug, 'label' => $type->name])
                ->values()
                ->all()),
            'stoneFilters' => $filters->toProps(),
            'pickedStones' => Inertia::optional(fn () => StoneCardResource::collection(
                $this->catalogService->stonesBySlugs(array_values(array_filter((array) $request->query('picked', []), 'is_string'))),
            )->resolve($request)),
        ];
    }
}

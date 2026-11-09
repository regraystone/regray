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
     * time like a brand's Our Collection, and only those offered for the chosen
     * service (`stone_service`). `pickedStones` is asked for by slug, for a
     * restored draft whose stones may be on no page the reader has reached.
     *
     * `stoneTypes` follows the service, so it is read on every request that asks
     * for it rather than once: a once prop is left out of a later full visit and
     * the browser would show the previous service's types.
     *
     * An edit passes its inquiry's service as `$serviceSlug`, used when the URL
     * names none, so the page opens on that service's stones.
     *
     * @return array<string, mixed>
     */
    public function execute(Request $request, ?string $serviceSlug = null): array
    {
        /** @var User $user */
        $user = $request->user();

        if ($serviceSlug !== null && ! $request->query->has('stone_service')) {
            $request->query->set('stone_service', $serviceSlug);
        }

        $filters = InquiryStoneFilterData::fromRequest($request);

        return [
            'stones' => Inertia::scroll(fn () => StoneCardResource::collection($this->catalogService->stones($user, $filters))),
            'stoneTypes' => fn () => $this->catalogService->stoneTypes($filters->service)
                ->map(fn (StoneType $type): array => ['value' => $type->slug, 'label' => $type->name])
                ->values()
                ->all(),
            'stoneFilters' => $filters->toProps(),
            'pickedStones' => Inertia::optional(fn () => StoneCardResource::collection(
                $this->catalogService->stonesBySlugs(
                    array_values(array_filter((array) $request->query('picked', []), 'is_string')),
                    $filters->service,
                ),
            )->resolve($request)),
        ];
    }
}

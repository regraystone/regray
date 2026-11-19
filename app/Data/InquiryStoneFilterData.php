<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use App\Models\Brand;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Which page of stones the Inquire page asks for: the service the stones are
 * for, and its tab, stone type, brands and search together. A value it does not
 * know is no filter, like any other bad value on a list, except the service:
 * with no active service there are no stones to list.
 *
 * `brands` (`stone_brands[]`, slugs) keeps the stones of any of those brands;
 * none is every brand. It is the reader's own filter on this question, and
 * may name brands their Brand answer did not.
 */
final readonly class InquiryStoneFilterData
{
    public const TAB_ALL = 'all';

    public const TAB_SAVED = 'saved';

    public const TAB_BEST_SELLERS = 'bestSellers';

    public function __construct(
        public string $tab,
        public ?string $type,
        public ?string $search,
        public int $page,
        public int $perPage,
        public ?string $service = null,
        /** @var list<string> */
        public array $brands = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $text = function (string $key) use ($request): ?string {
            $value = $request->query($key);

            return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), 100, '') : null;
        };

        $tab = $text('stone_tab');
        $service = $text('stone_service');
        $brands = array_values(array_unique(array_filter((array) $request->query('stone_brands', []), fn (mixed $slug): bool => is_string($slug) && $slug !== '')));

        return new self(
            tab: in_array($tab, [self::TAB_SAVED, self::TAB_BEST_SELLERS], true) ? $tab : self::TAB_ALL,
            type: $text('stone_type'),
            search: $text('stone_search'),
            page: max(1, (int) $text('page')),
            perPage: (HomeGridPageSize::tryFrom((int) $request->cookie(HomeGridPageSize::COOKIE)) ?? HomeGridPageSize::DEFAULT)->value,
            service: $service !== null && Service::query()->where('slug', $service)->where('is_active', true)->exists() ? $service : null,
            // Only active brands, in their own order; an unknown slug is no filter.
            brands: $brands === [] ? [] : array_values(Brand::query()->active()->whereIn('slug', array_slice($brands, 0, 50))->orderBy('sort_order')->pluck('slug')->all()),
        );
    }

    /** @return array{tab: string, type: string|null, search: string|null, perPage: int, service: string|null, brands: list<string>} */
    public function toProps(): array
    {
        return ['tab' => $this->tab, 'type' => $this->type, 'search' => $this->search, 'perPage' => $this->perPage, 'service' => $this->service, 'brands' => $this->brands];
    }
}

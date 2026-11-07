<?php

namespace App\Data;

use App\Enums\HomeGridPageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Which page of stones the Inquire page asks for: its tab, stone type and search
 * together. A value it does not know is no filter, like any other bad value on a
 * list.
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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $text = function (string $key) use ($request): ?string {
            $value = $request->query($key);

            return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), 100, '') : null;
        };

        $tab = $text('stone_tab');

        return new self(
            tab: in_array($tab, [self::TAB_SAVED, self::TAB_BEST_SELLERS], true) ? $tab : self::TAB_ALL,
            type: $text('stone_type'),
            search: $text('stone_search'),
            page: max(1, (int) $text('page')),
            perPage: (HomeGridPageSize::tryFrom((int) $request->cookie(HomeGridPageSize::COOKIE)) ?? HomeGridPageSize::DEFAULT)->value,
        );
    }

    /** @return array{tab: string, type: string|null, search: string|null, perPage: int} */
    public function toProps(): array
    {
        return ['tab' => $this->tab, 'type' => $this->type, 'search' => $this->search, 'perPage' => $this->perPage];
    }
}

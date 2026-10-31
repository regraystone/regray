<?php

namespace App\Services;

use App\Repositories\Contracts\StoneRepositoryInterface;

/**
 * Which stones are best sellers: the BEST_SELLER_COUNT active stones in the
 * most completed (closed) inquiries, each inquiry counted once per stone.
 * Stones tied with the last place are best sellers too, so there can be more.
 *
 * Worked out from the inquiries on first use and kept for the rest of the
 * request (the class is bound scoped), so a page of stone cards asks once and
 * a newly completed inquiry shows on the next request.
 */
class BestSellerService
{
    public const BEST_SELLER_COUNT = 10;

    /** @var array<int, true>|null */
    private ?array $bestSellerIds = null;

    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function isBestSeller(int $stoneId): bool
    {
        $this->bestSellerIds ??= array_fill_keys(
            $this->stoneRepository->bestSellerIds(self::BEST_SELLER_COUNT),
            true,
        );

        return isset($this->bestSellerIds[$stoneId]);
    }
}

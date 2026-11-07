<?php

namespace App\Services;

use App\Repositories\Contracts\StoneRepositoryInterface;

/**
 * Which stone types are best sellers: the BEST_SELLER_COUNT active
 * types in the most completed (closed) inquiries, through their active
 * stones, each inquiry counted once per type. Types tied with the last
 * place are best sellers too, so there can be more. A stone is a best seller
 * when its type is.
 *
 * Worked out from the inquiries on first use and kept for the rest of the
 * request (the class is bound scoped), so a page asks once and a newly
 * completed inquiry shows on the next request.
 */
class BestSellerService
{
    public const BEST_SELLER_COUNT = 3;

    /** @var array<int, true>|null */
    private ?array $bestSellerTypeIds = null;

    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function isBestSeller(int $stoneTypeId): bool
    {
        $this->bestSellerTypeIds ??= array_fill_keys(
            $this->stoneRepository->bestSellerTypeIds(self::BEST_SELLER_COUNT),
            true,
        );

        return isset($this->bestSellerTypeIds[$stoneTypeId]);
    }
}

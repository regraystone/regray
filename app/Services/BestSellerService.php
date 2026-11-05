<?php

namespace App\Services;

use App\Repositories\Contracts\StoneRepositoryInterface;

/**
 * Which stone categories are best sellers: the BEST_SELLER_COUNT active
 * categories in the most completed (closed) inquiries, through their active
 * stones, each inquiry counted once per category. Categories tied with the last
 * place are best sellers too, so there can be more. A stone is a best seller
 * when its category is.
 *
 * Worked out from the inquiries on first use and kept for the rest of the
 * request (the class is bound scoped), so a page asks once and a newly
 * completed inquiry shows on the next request.
 */
class BestSellerService
{
    public const BEST_SELLER_COUNT = 3;

    /** @var array<int, true>|null */
    private ?array $bestSellerCategoryIds = null;

    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function isBestSeller(int $stoneCategoryId): bool
    {
        $this->bestSellerCategoryIds ??= array_fill_keys(
            $this->stoneRepository->bestSellerCategoryIds(self::BEST_SELLER_COUNT),
            true,
        );

        return isset($this->bestSellerCategoryIds[$stoneCategoryId]);
    }
}

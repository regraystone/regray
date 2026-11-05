<?php

namespace App\Services;

use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StoneService
{
    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function getActiveStone(string $stoneSlug): Stone
    {
        $stone = $this->stoneRepository->findActiveBySlug($stoneSlug);

        if ($stone === null) {
            throw (new ModelNotFoundException)->setModel(Stone::class, [$stoneSlug]);
        }

        return $stone;
    }
}

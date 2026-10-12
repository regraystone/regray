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

    /** A nested lookup must confirm membership too; all lookup misses share the same HTTP 404. */
    public function getActiveStone(string $stoneSlug, ?string $projectSlug = null): Stone
    {
        $stone = $projectSlug === null
            ? $this->stoneRepository->findActiveBySlug($stoneSlug)
            : $this->stoneRepository->findActiveInPublishedProject($stoneSlug, $projectSlug);

        if ($stone === null) {
            throw (new ModelNotFoundException)->setModel(Stone::class, [$stoneSlug]);
        }

        return $stone;
    }
}

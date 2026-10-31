<?php

namespace App\Services;

use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\SavedStoneRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SavedStoneService
{
    public function __construct(
        public readonly SavedStoneRepositoryInterface $savedStoneRepository,
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    /**
     * @return array{saved: Collection<int, Stone>}
     */
    public function listFor(User $user): array
    {
        return [
            'saved' => $this->savedStoneRepository->activeStonesFor($user),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function slugsFor(User $user): array
    {
        return $this->savedStoneRepository->activeSlugsFor($user);
    }

    public function save(User $user, string $slug): void
    {
        $stone = $this->stoneRepository->findActiveSummaryBySlug($slug);

        if ($stone === null) {
            throw (new ModelNotFoundException)->setModel(Stone::class, [$slug]);
        }

        $this->savedStoneRepository->attach($user, $stone);
    }

    public function remove(User $user, string $slug): void
    {
        $stone = $this->stoneRepository->findActiveSummaryBySlug($slug);

        if ($stone === null) {
            return;
        }

        $this->savedStoneRepository->detach($user, $stone);
    }
}

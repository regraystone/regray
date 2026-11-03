<?php

namespace App\Services;

use App\Models\Furniture;
use App\Models\User;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use App\Repositories\Contracts\SavedFurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SavedFurnitureService
{
    public function __construct(
        public readonly SavedFurnitureRepositoryInterface $savedFurnitureRepository,
        public readonly FurnitureRepositoryInterface $furnitureRepository,
    ) {}

    /** @return Collection<int, Furniture> */
    public function listFor(User $user): Collection
    {
        return $this->savedFurnitureRepository->activeFurnitureFor($user);
    }

    /** @return array<int, string> */
    public function slugsFor(User $user): array
    {
        return $this->savedFurnitureRepository->activeSlugsFor($user);
    }

    public function save(User $user, string $slug): void
    {
        $furniture = $this->furnitureRepository->findActiveBySlug($slug);

        if ($furniture === null) {
            throw (new ModelNotFoundException)->setModel(Furniture::class, [$slug]);
        }

        $this->savedFurnitureRepository->attach($user, $furniture);
    }

    /** Unsaving something that is gone or inactive is not an error. */
    public function remove(User $user, string $slug): void
    {
        $furniture = $this->furnitureRepository->findActiveBySlug($slug);

        if ($furniture === null) {
            return;
        }

        $this->savedFurnitureRepository->detach($user, $furniture);
    }
}

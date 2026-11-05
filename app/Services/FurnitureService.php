<?php

namespace App\Services;

use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FurnitureService
{
    public function __construct(
        public readonly FurnitureRepositoryInterface $furnitureRepository,
    ) {}

    public function getActiveFurniture(string $slug): Furniture
    {
        $furniture = $this->furnitureRepository->findActiveBySlug($slug);

        if ($furniture === null) {
            throw (new ModelNotFoundException)->setModel(Furniture::class, [$slug]);
        }

        return $furniture;
    }
}

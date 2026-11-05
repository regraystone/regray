<?php

namespace App\Repositories\Contracts;

use App\Models\Furniture;

interface FurnitureRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Furniture;
}

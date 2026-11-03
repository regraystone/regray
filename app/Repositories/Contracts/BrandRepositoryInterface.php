<?php

namespace App\Repositories\Contracts;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

interface BrandRepositoryInterface
{
    /**
     * The active brands in their home page order.
     *
     * @return Collection<int, Brand>
     */
    public function active(): Collection;

    /** An active brand with its published projects, newest first. */
    public function findActiveBySlug(string $slug): ?Brand;
}

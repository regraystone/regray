<?php

namespace App\Repositories\Contracts;

use App\Models\Stone;
use Illuminate\Support\Collection;

interface StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone;

    public function findActiveInPublishedProject(string $stoneSlug, string $projectSlug): ?Stone;

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): Collection;
}

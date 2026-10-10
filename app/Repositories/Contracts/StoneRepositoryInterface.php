<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface StoneRepositoryInterface
{
    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): Collection;
}

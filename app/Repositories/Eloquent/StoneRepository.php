<?php

namespace App\Repositories\Eloquent;

use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Support\Collection;

class StoneRepository implements StoneRepositoryInterface
{
    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function activeFilterOptions(): Collection
    {
        return Stone::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Stone $stone): array => [
                'value' => $stone->slug,
                'label' => $stone->name,
            ]);
    }
}

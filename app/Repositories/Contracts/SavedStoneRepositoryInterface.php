<?php

namespace App\Repositories\Contracts;

use App\Models\Stone;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface SavedStoneRepositoryInterface
{
    /** Active, non-deleted stones the user saved, ordered by sort_order then name. */
    /**
     * @return Collection<int, Stone>
     */
    public function activeStonesFor(User $user): Collection;

    /** @return array<int, string> */
    public function activeSlugsFor(User $user): array;

    /** Attach using syncWithoutDetaching with created_at set (existing row keeps its created_at). */
    public function attach(User $user, Stone $stone): void;

    /** Detach the stone from the user's saved list. */
    public function detach(User $user, Stone $stone): void;
}

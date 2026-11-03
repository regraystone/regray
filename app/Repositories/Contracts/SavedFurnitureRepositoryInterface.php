<?php

namespace App\Repositories\Contracts;

use App\Models\Furniture;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface SavedFurnitureRepositoryInterface
{
    /**
     * The active furniture the user saved, newest save first.
     *
     * @return Collection<int, Furniture>
     */
    public function activeFurnitureFor(User $user): Collection;

    /** @return array<int, string> */
    public function activeSlugsFor(User $user): array;

    /** Save it; saving it again keeps the first save's created_at. */
    public function attach(User $user, Furniture $furniture): void;

    public function detach(User $user, Furniture $furniture): void;
}

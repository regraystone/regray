<?php

namespace App\Repositories\Eloquent;

use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\SavedStoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SavedStoneRepository implements SavedStoneRepositoryInterface
{
    /**
     * @return Collection<int, Stone>
     */
    public function activeStonesFor(User $user): Collection
    {
        return Stone::query()
            ->active()
            ->join('saved_stones', 'saved_stones.stone_id', '=', 'stones.id')
            ->where('saved_stones.user_id', $user->id)
            ->select(['stones.slug', 'stones.name', 'stones.thumbnail_path', 'stones.recommended'])
            ->orderByDesc('saved_stones.created_at')
            ->orderBy('stones.sort_order')
            ->orderBy('stones.name')
            ->get();
    }

    /** @return array<int, string> */
    public function activeSlugsFor(User $user): array
    {
        return Stone::query()
            ->active()
            ->whereHas('savedBy', fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderBy('stones.sort_order')
            ->orderBy('stones.name')
            ->pluck('stones.slug')
            ->all();
    }

    public function attach(User $user, Stone $stone): void
    {
        $user->savedStones()->syncWithoutDetaching([
            $stone->id => ['created_at' => Carbon::now()],
        ]);
    }

    public function detach(User $user, Stone $stone): void
    {
        $user->savedStones()->detach($stone->id);
    }
}

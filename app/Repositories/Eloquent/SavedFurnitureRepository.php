<?php

namespace App\Repositories\Eloquent;

use App\Models\Furniture;
use App\Models\User;
use App\Repositories\Contracts\SavedFurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SavedFurnitureRepository implements SavedFurnitureRepositoryInterface
{
    /** @return Collection<int, Furniture> */
    public function activeFurnitureFor(User $user): Collection
    {
        return Furniture::query()
            ->active()
            ->with('projectCategory:id,slug,name')
            ->join('saved_furniture', 'saved_furniture.furniture_id', '=', 'furniture.id')
            ->where('saved_furniture.user_id', $user->id)
            ->select(['furniture.id', 'furniture.project_category_id', 'furniture.slug', 'furniture.name', 'furniture.thumbnail_path'])
            ->orderByDesc('saved_furniture.created_at')
            ->orderBy('furniture.sort_order')
            ->orderBy('furniture.name')
            ->get();
    }

    /** @return array<int, string> */
    public function activeSlugsFor(User $user): array
    {
        return Furniture::query()
            ->active()
            ->whereHas('savedBy', fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderBy('furniture.sort_order')
            ->orderBy('furniture.name')
            ->pluck('furniture.slug')
            ->all();
    }

    public function attach(User $user, Furniture $furniture): void
    {
        $user->savedFurniture()->syncWithoutDetaching([
            $furniture->id => ['created_at' => Carbon::now()],
        ]);
    }

    public function detach(User $user, Furniture $furniture): void
    {
        $user->savedFurniture()->detach($furniture->id);
    }
}

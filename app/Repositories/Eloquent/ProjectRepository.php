<?php

namespace App\Repositories\Eloquent;

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(ProjectFilterData $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()
            ->published()
            ->select(['id', 'slug', 'name', 'thumbnail_path']);

        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhereHas('stones', function ($sq) use ($filters): void {
                        $sq->where('name', 'ILIKE', "%{$filters->search}%");
                    })
                    ->orWhereHas('categories', function ($sq) use ($filters): void {
                        $sq->where('name', 'ILIKE', "%{$filters->search}%");
                    });
            });
        }

        if ($filters->stone !== null) {
            $query->whereHas('stones', function ($q) use ($filters): void {
                $q->where('slug', $filters->stone);
            });
        }

        if ($filters->category !== null) {
            $query->whereHas('categories', function ($q) use ($filters): void {
                $q->where('slug', $filters->category);
            });
        }

        $query->orderByDesc('completed_year')->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }
}

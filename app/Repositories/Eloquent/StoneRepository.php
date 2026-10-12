<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class StoneRepository implements StoneRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?Stone
    {
        return $this->profileQuery()->where('slug', $slug)->first();
    }

    /** Membership is checked here, after permission, so forbidden readers cannot probe either slug. */
    public function findActiveInPublishedProject(string $stoneSlug, string $projectSlug): ?Stone
    {
        return $this->profileQuery()
            ->where('slug', $stoneSlug)
            ->whereHas('projects',
                /** @param Builder<Project> $projects */
                fn (Builder $projects) => $projects->published()->where('slug', $projectSlug)
            )
            ->first();
    }

    /**
     * Both doors expose the same profile. Keep their eager loads together and retain matching keys.
     * The model already orders the fact lists by pivot; advisory finishes belong only to inquiries.
     *
     * @return Builder<Stone>
     */
    private function profileQuery(): Builder
    {
        return Stone::query()->active()->with([
            'images' => fn (Relation $images) => $images->getQuery()
                ->select(['id', 'stone_id', 'name', 'path'])
                ->orderBy('sort_order'),
            'applications' => fn (Relation $applications) => $applications->getQuery()
                ->select(['applications.id', 'name']),
            'careTips' => fn (Relation $careTips) => $careTips->getQuery()
                ->select(['care_tips.id', 'tip']),
            'finishes' => fn (Relation $finishes) => $finishes->getQuery()
                ->select(['finishes.id', 'name'])
                ->where('is_active', true)
                ->where('is_advisory', false),
            'projects' => fn (Relation $projects) => $projects->getQuery()
                ->select(['projects.id', 'slug', 'name', 'thumbnail_path'])
                ->where('is_published', true)
                ->orderByDesc('completed_year')
                ->orderBy('name'),
        ]);
    }

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

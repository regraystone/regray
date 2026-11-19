<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StoneService
{
    public function __construct(
        public readonly StoneRepositoryInterface $stoneRepository,
    ) {}

    public function getActiveStone(string $stoneSlug): Stone
    {
        $stone = $this->stoneRepository->findActiveBySlug($stoneSlug);

        if ($stone === null) {
            throw (new ModelNotFoundException)->setModel(Stone::class, [$stoneSlug]);
        }

        return $stone;
    }

    /**
     * What a stone's page shows around it: the stones before and after it of
     * its brand and type (null at either end), up to `$relatedCount` others of
     * that type, and the published projects it was used in.
     *
     * @return array{previous: Stone|null, next: Stone|null, related: Collection<int, Stone>, projects: Collection<int, Project>}
     */
    public function surroundings(Stone $stone, int $relatedCount, int $projectCount): array
    {
        $stones = $this->stoneRepository->activeOfBrandAndType($stone->brand_id, $stone->stone_type_id)->values();
        $index = $stones->search(fn (Stone $other): bool => $other->id === $stone->id);

        return [
            'previous' => $index === false ? null : $stones->get($index - 1),
            'next' => $index === false ? null : $stones->get($index + 1),
            'related' => $this->stoneRepository->relatedTo($stone, $relatedCount),
            'projects' => $this->stoneRepository->publishedProjectsUsing($stone, $projectCount),
        ];
    }
}

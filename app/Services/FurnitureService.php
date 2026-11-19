<?php

namespace App\Services;

use App\Models\Furniture;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FurnitureService
{
    public function __construct(
        public readonly FurnitureRepositoryInterface $furnitureRepository,
    ) {}

    public function getActiveFurniture(string $slug): Furniture
    {
        $furniture = $this->furnitureRepository->findActiveBySlug($slug);

        if ($furniture === null) {
            throw (new ModelNotFoundException)->setModel(Furniture::class, [$slug]);
        }

        return $furniture;
    }

    /**
     * The pieces before and after this one in its brand's catalog order, or
     * null at either end.
     *
     * @return array{previous: Furniture|null, next: Furniture|null}
     */
    public function neighbours(Furniture $furniture): array
    {
        $pieces = $this->furnitureRepository->activeOfBrand($furniture->brand_id)->values();
        $index = $pieces->search(fn (Furniture $piece): bool => $piece->id === $furniture->id);

        if ($index === false) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $pieces->get($index - 1),
            'next' => $pieces->get($index + 1),
        ];
    }

    /**
     * Other active pieces of the same brand and type, to show under it.
     *
     * @return Collection<int, Furniture>
     */
    public function related(Furniture $furniture, int $limit): Collection
    {
        return $this->furnitureRepository->relatedTo($furniture, $limit);
    }
}

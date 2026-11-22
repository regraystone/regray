<?php

namespace App\Services;

use App\Models\Showcase;
use App\Repositories\Contracts\ShowcaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowcaseService
{
    public function __construct(
        public readonly ShowcaseRepositoryInterface $showcaseRepository,
    ) {}

    /**
     * The most recently completed published showcases, newest first: the landing page's showcase.
     *
     * @return Collection<int, Showcase>
     */
    public function latestPublished(int $limit): Collection
    {
        return $this->showcaseRepository->latestPublished($limit);
    }

    /** Keep a missing published showcase a domain lookup failure; the HTTP handler renders its 404. */
    public function getPublishedShowcase(string $stoneSlug, string $showcaseSlug, bool $withArchived = false): Showcase
    {
        $showcase = $this->showcaseRepository->findPublishedInStone($stoneSlug, $showcaseSlug, $withArchived);

        if ($showcase === null) {
            throw (new ModelNotFoundException)->setModel(Showcase::class, [$showcaseSlug]);
        }

        return $showcase;
    }

    public function findPublished(string $slug): Showcase
    {
        $showcase = $this->showcaseRepository->findPublishedBySlug($slug);

        if ($showcase === null) {
            throw (new ModelNotFoundException)->setModel(Showcase::class, [$slug]);
        }

        return $showcase;
    }
}

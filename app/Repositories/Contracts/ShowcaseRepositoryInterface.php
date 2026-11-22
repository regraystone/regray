<?php

namespace App\Repositories\Contracts;

use App\Models\Showcase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ShowcaseRepositoryInterface
{
    /**
     * The most recently completed published showcases, newest first.
     *
     * @return Collection<int, Showcase>
     */
    public function latestPublished(int $limit): Collection;

    /**
     * A published showcase under one of its active stones. `$withArchived`
     * finds an archived one too: the saved stone's showcases keep it.
     */
    public function findPublishedInStone(string $stoneSlug, string $showcaseSlug, bool $withArchived = false): ?Showcase;

    public function findPublishedBySlug(string $slug): ?Showcase;

    /**
     * The admin's Showcase page: every showcase but the archived, drafts too,
     * newest completed first, then by name, a page at a time. The search
     * (already escaped for ILIKE) matches the showcase's name only. Each comes
     * with its stones, categories and gallery.
     *
     * @return LengthAwarePaginator<int, Showcase>
     */
    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator;

    /**
     * What the Add and Edit showcase form offers: the active stones and
     * categories, each as an id and a label.
     *
     * @return array{stones: list<array{id: int, name: string, brand: string}>, categories: list<array{id: int, name: string}>}
     */
    public function adminOptions(): array;
}

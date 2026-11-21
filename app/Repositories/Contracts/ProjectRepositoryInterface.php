<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    /**
     * The most recently completed published projects, newest first.
     *
     * @return Collection<int, Project>
     */
    public function latestPublished(int $limit): Collection;

    /**
     * A published project under one of its active stones. `$withArchived`
     * finds an archived one too: the saved stone's projects keep it.
     */
    public function findPublishedInStone(string $stoneSlug, string $projectSlug, bool $withArchived = false): ?Project;

    public function findPublishedBySlug(string $slug): ?Project;

    /**
     * The admin's Projects page: every project but the archived, drafts too,
     * newest completed first, then by name, a page at a time. The search
     * (already escaped for ILIKE) matches the project's name only. Each comes
     * with its brand, stones, categories and gallery.
     *
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator;

    /**
     * What the Add and Edit project form offers: the active brands, stones
     * and categories, each as an id and a label.
     *
     * @return array{brands: list<array{id: int, name: string}>, stones: list<array{id: int, name: string, brand: string}>, categories: list<array{id: int, name: string}>}
     */
    public function adminOptions(): array;
}

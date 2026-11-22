<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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
     * with its stones, categories and gallery.
     *
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForAdmin(?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator;

    /**
     * What the Add and Edit project form offers: the active stones and
     * categories, each as an id and a label.
     *
     * @return array{stones: list<array{id: int, name: string, brand: string}>, categories: list<array{id: int, name: string}>}
     */
    public function adminOptions(): array;
}

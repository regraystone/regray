<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A project as a card on the admin's Projects page, and what its View drawer
 * and Edit form read.
 *
 * `actions` is what this reader may do to this project, from the permission
 * each route checks, so the page never decides by role. `thumbnailFile` and
 * each photo's `file` are the uploaded images' own names and sizes, to show
 * people; null for an image that was committed rather than uploaded.
 * `publicUrl` is the project's public page, or null while it is a draft.
 *
 * Expects its stones, categories and gallery loaded
 * (ProjectRepositoryInterface::paginateForAdmin()).
 *
 * @mixin Project
 */
class AdminProjectResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, name: string, location: string, completedYear: int, overview: string, thumbnail: string, thumbnailFile: array{originalName: string, sizeBytes: int|null}|null, stones: list<array{id: int, name: string}>, categories: list<array{id: int, name: string}>, images: list<array{id: int, url: string, name: string|null, file: array{originalName: string, sizeBytes: int|null}|null}>, isPublished: bool, publishedAt: string|null, createdAt: string, updatedAt: string, publicUrl: string|null, actions: list<string>}
     */
    public function toArray(Request $request): array
    {
        $gate = Gate::forUser($request->user());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'location' => $this->location,
            'completedYear' => $this->completed_year,
            'overview' => $this->overview,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'thumbnailFile' => self::file($this->thumbnail_original_name, $this->thumbnail_size_bytes),
            'stones' => array_values($this->stones->map(fn (Stone $stone): array => ['id' => $stone->id, 'name' => $stone->name])->all()),
            'categories' => array_values($this->categories->map(fn (ProjectCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all()),
            'images' => array_values($this->gallery->map(fn (ProjectImage $image): array => [
                'id' => $image->id,
                'url' => ProjectCardResource::resolveThumbnailUrl($image->path),
                'name' => $image->label(),
                'file' => self::file($image->original_name, $image->size_bytes),
            ])->all()),
            'isPublished' => $this->is_published,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'publicUrl' => $this->is_published ? route('stone.show', $this->slug) : null,
            'actions' => array_values(array_filter([
                'view',
                $gate->allows(Permission::ProjectsUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::ProjectsArchive->value) ? 'archive' : null,
            ])),
        ];
    }

    /** @return array{originalName: string, sizeBytes: int|null}|null */
    private static function file(?string $originalName, ?int $sizeBytes): ?array
    {
        return $originalName === null ? null : ['originalName' => $originalName, 'sizeBytes' => $sizeBytes];
    }
}

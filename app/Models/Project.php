<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A finished project, with its public page at `/{slug}`, set on the admin's
 * Projects page.
 *
 * `thumbnail_path` and each gallery photo's `path` are paths on the public
 * disk (`config('filesystems.public_disk')`). An uploaded image is stored
 * under a random name (StoredFileName); its own name, type and size are kept
 * beside the path, to show to people only.
 *
 * A draft (`is_published` false) has no page; `published_at` is when it was
 * first published. Archiving is the soft delete (`deleted_at`): an archived
 * project is gone from every reader but the saved one (saved/{stone}/{project}).
 * Its slug stays taken.
 *
 * @property int $id
 * @property int|null $brand_id
 * @property string $slug
 * @property string $name
 * @property int $completed_year
 * @property string $location
 * @property string $overview
 * @property string $thumbnail_path
 * @property string|null $thumbnail_original_name
 * @property string|null $thumbnail_mime_type
 * @property int|null $thumbnail_size_bytes
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ProjectImage> $images
 * @property-read Collection<int, Stone> $stones
 * @property-read Stone $parentStone The active stone this profile was requested under.
 * @property-read Collection<int, ProjectCategory> $categories
 * @property-read Brand|null $brand
 */
#[Fillable(['brand_id', 'slug', 'name', 'completed_year', 'location', 'overview', 'thumbnail_path', 'is_published', 'published_at'])]
class Project extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'thumbnail_size_bytes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<ProjectImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class);
    }

    /**
     * Its gallery, in the order its page shows it.
     *
     * @return HasMany<ProjectImage, $this>
     */
    public function gallery(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Stone, $this> */
    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'project_stone');
    }

    /** @return BelongsToMany<ProjectCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'category_project');
    }
}

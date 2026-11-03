<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brand on the home page, with its own profile page at `/brands/{slug}`.
 *
 * `logo_path` and `thumbnail_path` are paths on the public disk
 * (`config('filesystems.public_disk')`), resolved to URLs through
 * ProjectCardResource::resolveThumbnailUrl.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $logo_path
 * @property string|null $thumbnail_path
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $website_url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['slug', 'name', 'logo_path', 'thumbnail_path', 'tagline', 'description', 'website_url', 'is_active', 'sort_order'])]
class Brand extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}

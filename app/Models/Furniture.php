<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A furniture piece in the home page's Furniture collection, under one
 * project type. `thumbnail_path` is a path on the public disk, or null for
 * the neutral placeholder.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property int $project_category_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProjectCategory $projectCategory
 */
#[Fillable(['slug', 'name', 'description', 'thumbnail_path', 'project_category_id', 'is_active', 'sort_order'])]
class Furniture extends Model
{
    /** "Furniture" is its own plural, so the table is named here rather than guessed. */
    protected $table = 'furniture';

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

    /** @return BelongsToMany<User, $this> */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_furniture')
            ->withPivot('created_at');
    }

    /** @return BelongsTo<ProjectCategory, $this> */
    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class);
    }
}

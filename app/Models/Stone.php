<?php

namespace App\Models;

use Database\Factories\StoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stone_category_id
 * @property string $slug
 * @property string $name
 * @property string $thumbnail_path
 * @property string $size
 * @property int $thickness_mm
 * @property bool $recommended
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, StoneImage> $images
 * @property-read StoneCategory $category
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['stone_category_id', 'slug', 'name', 'thumbnail_path', 'size', 'thickness_mm', 'recommended', 'is_active', 'sort_order'])]
class Stone extends Model
{
    /** @use HasFactory<StoneFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'recommended' => 'boolean',
            'is_active' => 'boolean',
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

    /** @return HasMany<StoneImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(StoneImage::class);
    }

    /** @return BelongsTo<StoneCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StoneCategory::class, 'stone_category_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_stones')
            ->withPivot('created_at');
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_stone');
    }
}

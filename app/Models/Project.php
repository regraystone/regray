<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $completed_year
 * @property string $location
 * @property string $overview
 * @property string $thumbnail_path
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['slug', 'name', 'completed_year', 'location', 'overview', 'thumbnail_path', 'is_published', 'published_at'])]
class Project extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function published()
    {
        return $this->where('is_published', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class);
    }

    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'project_stone');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'category_project');
    }
}

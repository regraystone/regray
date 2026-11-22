<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $image_path
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Showcase> $showcases
 * @property-read Collection<int, Furniture> $furniture
 */
#[Fillable(['slug', 'name', 'image_path', 'is_active', 'sort_order'])]
class ShowcaseCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Showcase, $this> */
    public function showcases(): BelongsToMany
    {
        return $this->belongsToMany(Showcase::class, 'category_showcase');
    }

    /** @return HasMany<Furniture, $this> */
    public function furniture(): HasMany
    {
        return $this->hasMany(Furniture::class);
    }
}

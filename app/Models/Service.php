<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $image_path
 * @property bool $is_standalone
 * @property string $selection_group
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['slug', 'name', 'image_path', 'is_standalone', 'selection_group', 'is_active', 'sort_order'])]
class Service extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_standalone' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return Builder<Service> */
    public function active(): Builder
    {
        return $this->where('is_active', true);
    }

    /** @return HasMany<ServiceFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(ServiceFeature::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brand on the home page, with its own page and collection at `/brands/{slug}`.
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
 * @property-read Collection<int, Stone> $stones
 * @property-read Collection<int, Furniture> $furniture
 */
#[Fillable(['slug', 'name', 'logo_path', 'thumbnail_path', 'tagline', 'description', 'website_url', 'is_active', 'sort_order'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

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
     * Only an active brand has a page, on either the guest or the signed-in route.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return self::query()->active()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * A product resolves only under its own brand and only while it is active.
     * The relation already leaves soft-deleted stones out.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return EloquentModel|null
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->resolveChildRouteBindingQuery($childType, $value, $field)->where('is_active', true)->first();
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

    /** @return HasMany<Stone, $this> */
    public function stones(): HasMany
    {
        return $this->hasMany(Stone::class);
    }

    /** @return HasMany<Furniture, $this> */
    public function furniture(): HasMany
    {
        return $this->hasMany(Furniture::class);
    }
}

<?php

namespace App\Models;

use App\Services\BestSellerService;
use Database\Factories\StoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $brand_id
 * @property int $stone_type_id
 * @property string $slug
 * @property string $name
 * @property string $thumbnail_path
 * @property string $size
 * @property int $thickness_mm
 * @property bool $is_active
 * @property string $origin
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_best_seller
 * @property-read Collection<int, StoneImage> $images
 * @property-read Brand $brand
 * @property-read StoneType $type
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, StoneType> $types
 */
#[Fillable(['brand_id', 'stone_type_id', 'slug', 'name', 'thumbnail_path', 'size', 'thickness_mm', 'origin', 'is_active', 'sort_order'])]
class Stone extends Model
{
    /** @use HasFactory<StoneFactory> */
    use HasFactory;

    use SoftDeletes;

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

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The active stones offered for this service: those whose primary type is
     * linked to it. The extra types in `stone_stone_type` are the collection
     * filters' and play no part.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function offeredFor(Builder $query, Service $service): void
    {
        $query->where('stones.is_active', true)
            ->whereIn('stones.stone_type_id', $service->stoneTypes()->getQuery()->select('stone_types.id'));
    }

    /**
     * Whether the stone's type is a best seller. Asked of the request's one
     * ranking, never stored.
     *
     * @return Attribute<bool, never>
     */
    protected function isBestSeller(): Attribute
    {
        return Attribute::get(fn (): bool => app(BestSellerService::class)->isBestSeller($this->stone_type_id));
    }

    /** @return HasMany<StoneImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(StoneImage::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<StoneType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(StoneType::class, 'stone_type_id');
    }

    /** @return BelongsToMany<StoneType, $this> */
    public function types(): BelongsToMany
    {
        return $this->belongsToMany(StoneType::class, 'stone_stone_type');
    }

    /** List the stone under its primary type when importing, without removing additional ones. */
    public function attachPrimaryFilterOptions(): void
    {
        $this->types()->syncWithoutDetaching([$this->stone_type_id]);
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

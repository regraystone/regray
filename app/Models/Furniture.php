<?php

namespace App\Models;

use Database\Factories\FurnitureFactory;
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
 * A furniture piece in its brand's collection.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property int $furniture_type_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at Archived (the soft delete) from the Products page.
 * @property-read Brand $brand
 * @property-read FurnitureType $furnitureType
 * @property-read Collection<int, FurnitureImage> $images
 */
#[Fillable(['brand_id', 'slug', 'name', 'description', 'thumbnail_path', 'furniture_type_id', 'is_active', 'sort_order'])]
class Furniture extends Model
{
    /** @use HasFactory<FurnitureFactory> */
    use HasFactory;

    use SoftDeletes;

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
     * The furniture the Products page lists, active or not: of a brand that is
     * not archived. An archived brand's are kept only as history, so nothing
     * on the Products page, nor its lists, counts or changes them.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ofLiveBrand(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('brand_id'), Brand::query()->notArchived()->select('id'));
    }

    /**
     * The furniture shown on Regray: active, of a brand that is not archived.
     * A saved piece keeps reading it without this.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true)
            ->whereIn($query->qualifyColumn('brand_id'), Brand::query()->notArchived()->select('id'));
    }

    /** @return BelongsTo<FurnitureType, $this> */
    public function furnitureType(): BelongsTo
    {
        return $this->belongsTo(FurnitureType::class)->withTrashed();
    }

    /**
     * Its gallery: its photos other than the thumbnail, in order.
     *
     * @return HasMany<FurnitureImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(FurnitureImage::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_furniture')
            ->withPivot('created_at');
    }
}

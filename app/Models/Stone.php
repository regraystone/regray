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
 * @property bool $is_active
 * @property string $origin
 * @property string|null $size_note How big the pieces run when the stone is sold without set sizes: "Diameter 15–40 cm".
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_best_seller
 * @property-read Collection<int, StoneImage> $images
 * @property-read Brand $brand
 * @property-read StoneType $type
 * @property-read Collection<int, Showcase> $showcases
 * @property-read Collection<int, StoneType> $types
 * @property-read Collection<int, StoneVariant> $variants
 * @property-read Collection<int, Thickness> $thicknesses
 * @property-read Collection<int, Size> $sizes
 */
#[Fillable(['brand_id', 'stone_type_id', 'slug', 'name', 'thumbnail_path', 'origin', 'size_note', 'is_active', 'sort_order'])]
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
     * The stones shown on Regray: active, of a brand that is not archived. A
     * saved stone or a past inquiry's keeps reading it without this.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true)
            ->whereIn($query->qualifyColumn('brand_id'), Brand::query()->notArchived()->select('id'));
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
            ->whereIn('stones.brand_id', Brand::query()->notArchived()->select('id'))
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

    /**
     * The slabs the stone comes in, each a size at a thickness, in the order
     * its profile and its Inquire row list them.
     *
     * @return HasMany<StoneVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(StoneVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The thicknesses some slab of the stone comes in, each once however many
     * sizes share it: by `sort_order` (its unit), then thinnest first. Count
     * the rows read, not the query: COUNT(*) counts every slab.
     *
     * @return BelongsToMany<Thickness, $this>
     */
    public function thicknesses(): BelongsToMany
    {
        return $this->belongsToMany(Thickness::class, 'stone_variants')
            ->distinct()
            ->orderBy('thicknesses.sort_order')
            ->orderBy('thicknesses.value');
    }

    /**
     * The sizes some slab of the stone comes in, each once however many
     * thicknesses it is cut in (a variant with no size names none): by
     * `sort_order` (its unit), then smallest first. Count the rows read, not
     * the query: COUNT(*) counts every slab.
     *
     * @return BelongsToMany<Size, $this>
     */
    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'stone_variants')
            ->distinct()
            ->orderBy('sizes.sort_order')
            ->orderBy('sizes.width')
            ->orderBy('sizes.length');
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

    /** @return BelongsToMany<Showcase, $this> */
    public function showcases(): BelongsToMany
    {
        return $this->belongsToMany(Showcase::class, 'showcase_stone');
    }
}

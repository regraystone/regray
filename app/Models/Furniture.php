<?php

namespace App\Models;

use Database\Factories\FurnitureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property-read Brand $brand
 * @property-read FurnitureType $furnitureType
 */
#[Fillable(['brand_id', 'slug', 'name', 'description', 'thumbnail_path', 'furniture_type_id', 'is_active', 'sort_order'])]
class Furniture extends Model
{
    /** @use HasFactory<FurnitureFactory> */
    use HasFactory;

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
        return $this->belongsTo(FurnitureType::class);
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
